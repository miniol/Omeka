<?php
/**
 * Exhibit Page
 * @package: Omeka
 */
class SectionPage extends Kea_Record
{
	public function setTableDefinition()
    {
		$this->option('type', 'MYISAM');
		$this->setTableName('section_pages');
		$this->hasColumn("section_id", "integer",null,"notnull");
	
		$this->hasColumn("layout", "string",255,"notblank");
		$this->hasColumn("page_order as order", "integer",null,"notnull");
		$this->setAttribute(Doctrine::ATTR_COLL_KEY, 'page_order');
	}
	
	public function setUp()
    {
		$this->hasOne("Section", "SectionPage.section_id");
		$this->ownsMany("ItemsPages", "ItemsPages.page_id");
	//	$this->hasMany("Item as Items", "ItemsPages.item_id");
	}
	
	public function delete()
	{
		$section = $this->Section;
		$retVal = parent::delete();
		
		$section->reorderPages();
	}
	
	public function getItemCount()
	{
		$sql = "SELECT COUNT(*) FROM ".$this->getTableName('ItemsPages')." p WHERE p.page_id = ? AND p.item_id IS NOT NULL";
		return $this->execute($sql, array($this->id), true);
	}
	
	public function getTextCount()
	{
		$sql = "SELECT COUNT(*) FROM ".$this->getTableName('ItemsPages')." p WHERE p.page_id = ? AND p.text IS NOT NULL";
		return $this->execute($sql, array($this->id), true);
	}
	
	/**
	 * Page Form POST will look like:
	 *
	 * Text[1] = 'Text inserted <a href="foobar.com">With HTML</a>'
	 * Item[2] = 35		(integer ID)
	 * Item[3] = 64
	 * Text[3] = 'This is commentary for the Item with ID # 64' 
	 * 
	 * @return void
	 **/
	public function preCommitForm(&$post, $options)
	{
		if(!empty($post['Text'])) {
			//Process the text fields
			foreach ($post['Text'] as $key => $text) {
				$ip = $this->ItemsPages[$key];
				$ip->text = $ip->strip($text);
				$ip->order = $key;
			}
		}
		
		if(!empty($post['Item'])) {
			//Process the Item fields
			foreach ($post['Item'] as $key => $item_id) {
				$ip = $this->ItemsPages[$key];
				$ip->item_id = is_numeric($item_id) ? $item_id : null;
				$ip->order = $key;
			}
		}
	}
	
	/**
	 * Retrieve the text at a given order on the page
	 *
	 * @return string|null	
	 **/
	public function Text($order) {
		
		$sql = "SELECT text FROM items_section_pages p WHERE p.page_id = ? AND p.entry_order = ? LIMIT 1";
		$res = $this->execute($sql,array($this->id, $order), true);
		return $res;
	}
	
/*
		public function Item($order) {
		$dql = "SELECT i.* FROM Item i INNER JOIN i.ItemsPages ip INNER JOIN ip.Page p WHERE p.order = ? AND p.id = ? LIMIT 1";
		$item = $this->executeDql($dql, array($order, $this->id));
		Zend::dump( get_class($item) );exit;
	}
*/	
	
	/**
	 * Retrieve the ID of the Item at a given order
	 *
	 * @return int|null
	 **/
	public function ItemId($order) {
		$sql = "SELECT item_id FROM items_section_pages p WHERE p.page_id = ? AND p.entry_order = ? LIMIT 1";
		$res = $this->execute($sql,array($this->id, $order), true);
		return $res;
	}
}
?>
