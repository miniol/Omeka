<?php
require_once 'Kea/Strategy/Interface.php';
/**
 * Taggable
 * Adaptation of the Rails Acts_as_taggable
 * @package: Omeka
 */
class Taggable implements Kea_Strategy_Interface
{
	protected $record;
	
	public function __construct(Kea_Record $record) {

		$this->record = $record;
		
		$this->type = get_class($record);
		
		$this->tagTable = Zend::Registry('doctrine')->getTable('Tag');
		
		$this->joinTable = Zend::Registry( 'doctrine' )->getTable('Taggings');
		
		$this->conn = Doctrine_Manager::getInstance()->connection();
	}
		
	public function __call($m, $a)
	{
		return call_user_func_array( array($this->record, $m), $a);
	}
	
	/**
	 * Bit of a hook to help with deleting Taggings (or anything else)
	 * 
	 * This particular method allows us to delete Taggings without setting up
	 * dependencies like Item::ownsMany(ItemsTaggings) via Doctrine
	 * which doesn't work b/c Doctrine doesn't allow for polymorphic relationships
	 *
	 * @return void
	 **/
	public function onDelete() {}
	
	public function onSave() {}
	
	public function getTaggings()
	{
		return $this->joinTable->findBy(array('record'=>$this->record));
	}
	
	public function getTags()
	{
		return $this->tagTable->findBy(array('record'=>$this->record, 'return'=>'object'), $this->type);
	}

	public function userTags($user)
	{
		$tags = $this->tagTable->findBy(array('user'=>$user, 'record'=>$this->record, 'return'=>'object'), $this->type);
		return $tags;
	}

	/**
	 * Delete a tag from the record
	 *
	 * @param int user_id The user to specifically delete the tag from
	 * @param bool deleteAll Whether or not to delete all references to this tag for this record
	 * @return bool|array Whether or not the tag was deleted (false if tag doesn't exist)
	 **/
	public function deleteTag($tag, $user=null, $deleteAll=false)
	{
		$joinType = 'Taggings';
			
		$findWith['tag'] = $tag;
		$findWith['record'] = $this->record;
		
		//If we aren't deleting all the tags associated with a record, then find those specifically for the user
		if(!$deleteAll) {
			$findWith['user'] = $user;
		}
		
		$tagging = $this->joinTable->findBy($findWith);
		
		return $tagging->delete();
	}
			
	/** If the $tag were a string and the keys of Tags were just the names of the tags, this would be:
	 * in_array(array_keys($this->Tags))
	 *
	 * @return boolean
	 **/
	public function hasTag($tag, $user=null) {
		$count = $this->joinTable->findBy(array('tag'=>$tag, 'user'=>$user, 'record'=>$this->record), null, true);
		
		return $count > 0;
	}	
		
	public function tagString($wrap = null, $delimiter = ',') {
		$string = '';
		$tags = $this->record->Tags;
		
		foreach( $this->record->Tags as $key => $tag )
		{
			if($tag->exists()) {
				$name = $tag->__toString();
				$string .= (!empty($wrap) ? preg_replace("/$name/", $wrap, $name) : $name);
				$string .= ( ($key+1) < $this->record->Tags->count() ) ? $delimiter.' ' : '';
			}
		}
		
		return $string;
	}

	public function addTags($tags, $user, $delimiter = ',') {
		if(!$this->record->id) {
			throw new Exception( 'A valid record ID # must be provided when tagging.' );
		}
		
		if(!$user) {
			throw new Exception( 'A valid user ID # must be provided when tagging' );
		}
		
		if(!is_array($tags)) {
			$tags = explode($delimiter, $tags);
			$tags = array_diff($tags, array(''));
		}
		
		foreach ($tags as $key => $tagName) {
			$tag = $this->tagTable->findOrNew($tagName);
			
			$tag->save();
			
			$join = new Taggings;
						
			$join->tag_id = $tag->id;
			$join->relation_id = $this->record->id;
			$join->type = $this->type;
			$join->Entity = $user->Entity;
			
			$join->trySave();			
		}
	}

	/**
	 * Calculate the difference between a tag string and a set of tags
	 *
	 * @return array Keys('removed','added')
	 **/
	public function diffTagString( $string, $tags=null, $delimiter=",")
	{
		if(!$tags)
		{
			$tags = $this->record->Tags;
		}
		
		$inputTags = array();
		$existingTags = array();
		$inputTags = explode($delimiter,$string);
		
		foreach ($inputTags as $key => $inputTag) {
			$inputTags[$key] = trim($inputTag);
		}
		
		//get rid of empty tags
		$inputTags = array_diff($inputTags,array(''));
		
		foreach ($tags as $key => $tag) {
			if($tag instanceof Tag || is_array($tag)) {
				$existingTags[$key] = trim($tag["name"]);
			}else{
				$existingTags[$key] = trim($tag);
			}
			
		}
		if(!empty($existingTags)) {
			$removed = array_values(array_diff($existingTags,$inputTags));
		}
		
		if(!empty($inputTags)) {
			$added = array_values(array_diff($inputTags,$existingTags));
		}
		return compact('removed','added');
	}	

	/**
	 * This will add tags that are in the tag string and remove those that are no longer in the tag string
	 *
	 * @return void
	 **/
	public function applyTagString($string, $user, $deleteTags = false, $delimiter=",")
	{
		$tags = ($deleteTags) ? $this->record->Tags : $this->userTags($user);
		$diff = $this->diffTagString($string, $tags, $delimiter);
		
		if(!empty($diff['removed'])) {
			$this->removeTagsByArray($diff['removed'], $user, $deleteTags);
			
			//PLUGIN HOOKS
			$this->pluginHook('onUntag' . get_class($this->record), array($this->record, $diff['removed'], $user));
		}
		if(!empty($diff['added'])) {
			$this->addTags($diff['added'], $user);
			
			//PLUGIN HOOKS
			$this->pluginHook('onTag' . get_class($this->record), array($this->record, $diff['added'], $user));
		}
		
	}
	
	public function removeTagsByArray($array,$user, $deleteWholeTag=false)
	{
		/*
		 *	Process for deleting the whole tag
		 *		1) delete all references for that tag/record combo within the relevant join table
		 *		2) check to see if there are any other references in that or the other join tables
		 *		3) if there are no references in the other join tables, delete the entry from the tag table
		 *
		 *		Right now this only does 1), not sure of the best way to determine which references a tag contains
		 */		
		foreach ($array as $tagName) {
			if($this->deleteTag($tagName, $user, $deleteWholeTag)) {
				$success = true;
			}
		}		
	}

}

?>