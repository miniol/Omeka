<?php
/**
 * @copyright Roy Rosenzweig Center for History and New Media, 2007-2010
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 * @subpackage Models
 */    
 
/**
 * @package Omeka
 * @subpackage Models
 * @author CHNM
 * @copyright Roy Rosenzweig Center for History and New Media, 2007-2010
 */
class ElementTable extends Omeka_Db_Table
{   
    /**
     * Find all the Element records that have a specific record type or the
     * record type 'All', indicating that these elements would apply to any
     * record type.
     * 
     * @param string
     * @return array
     */
    public function findByRecordType($recordTypeName)
    {
        $select = $this->getSelect();
        $db = $this->getDb();

        // Join against the record_types table to pull only elements for Items
         $select->joinInner(array('record_types'=> $db->RecordType),
                             'record_types.id = elements.record_type_id',
                             array('record_type_name'=>'record_types.name'));
        $select->where('record_types.name = ? OR record_types.name = "All"', $recordTypeName);
        
        $this->orderElements($select);
        
        return $this->fetchObjects($select);
    }
    
    public function findForFilesByMimeType($mimeType = null)
    {
        $db = $this->getDb(); 
        $sqlMimeTypeElements = $this->getSelect()
        ->joinInner(array('mime_element_set_lookup' => $db->MimeElementSetLookup), 'mime_element_set_lookup.element_set_id = element_sets.id', array())
        ->where('mime_element_set_lookup.mime = ?', $mimeType);
        
        return $this->fetchObjects($sqlMimeTypeElements);        
    }
    
    /**
     * Overriding getSelect() to always return the type_name and type_regex
     * for retrieved elements.
     * 
     * @return Omeka_Db_Select
     */
    public function getSelect()
    {
        $select = parent::getSelect();
        $db = $this->getDb();
            
        // Join on the element_sets table to retrieve set name
        $select->joinLeft(array('element_sets'=>$db->ElementSet), 'element_sets.id = elements.element_set_id',
            array('set_name'=>'element_sets.name'));
        return $select;
    }
    
    /**
     * Return the element's name and id for <select> tags on it.
     * 
     * @see Omeka_Db_Table::findPairsForSelectForm()
     * @param string
     * @return void
     */
    protected function _getColumnPairs()
    {
        return array('elements.id', 'elements.name');
    }
        
    protected function orderElements($select)
    {
        // ORDER BY e.order ASC, es.name ASC
        // This SQL statement will return results ordered each element set,
        // and for each element set these will be in the proper designated order.
        $select->order('elements.order ASC');
        $select->order('elements.element_set_id ASC');
    }
    
    /**
     * Retrieve all elements for a set.
     * 
     * @see display_element_set_form()
     * @param string The name of the set to which elements belong.
     * @return Element
     */
    public function findBySet($elementSet)
    {
        // Select all the elements for a given set
        $select = $this->getSelect();
        $db = $this->getDb();
        
        $select->where('element_sets.name = ?', (string) $elementSet);
        
        $this->orderElements($select);
        
        return $this->fetchObjects($select);       
    }
    
    /**
     * Retrieve a set of Element records that belong to a specific Item Type.
     * 
     * @see Item::getItemTypeElements()
     * @param integer
     * @return array Set of element records.
     */
    public function findByItemType($itemTypeId)
    {
        $select = $this->getSelect();
        $db = $this->getDb();
        $select->joinInner(array('item_types_elements'=>$db->ItemTypesElements), 'item_types_elements.element_id = elements.id', array());
        $select->where('item_types_elements.item_type_id = ?');
        $select->order('item_types_elements.order ASC');
        
        $elements = $this->fetchObjects($select, array($itemTypeId)); 

       return $elements;
    }
    
    public function findByElementSetNameAndElementName($elementSetName, $elementName)
    {
        $select = $this->getSelectForFindBy(array('element_set_name' => $elementSetName, 'element_name' => $elementName));
        return $this->fetchObject($select);
    }
    
    /**
     * Manipulate a Select object based on a set of criteria.
     * 
     * @param Omeka_Db_Select $select
     * @param array $params Possible parameters include:
     * <ul>
     *      <li>record_types - array - Usually one or more of the following:
     * All, Item, File</li>
     *      <li>sort - string - One of the following values: alpha</li>
     *      <li>element_set_name - string - Name of the element set to which
     * results should belong.</li>
     * </ul>
     */
    public function applySearchFilters($select, $params)
    {
        $db = $this->getDb();
        
        // Retrieve only elements matching a specific record type.
        if (array_key_exists('record_types', $params)) {
            $select->joinInner(array('record_types'=> $db->RecordType),
                                 'record_types.id = elements.record_type_id',
                                 array('record_type_name'=>'record_types.name'));
            $where = array();
            foreach ($params['record_types'] as $recordTypeName) {
                $where[] = 'record_types.name = ' . $db->quote($recordTypeName);
            }
            $select->where('(' . join(' OR ', $where) . ')');
        }
        
        if (array_key_exists('sort', $params)) {
            if ($params['sort'] == 'alpha') {
                $select->order('elements.name ASC');
            } else if ($params['sort'] == 'alphaBySet') {
                $select->order('element_sets.name ASC')->order('elements.name ASC');
            }
        }
        
        if (array_key_exists('element_set_name', $params)) {
            $select->where('element_sets.name = binary ?', (string) $params['element_set_name']);
        }

        if (array_key_exists('element_name', $params)) {
            $select->where('elements.name = binary ?', (string) $params['element_name']); 
        }

        // Retrive results including, but not limited to, a specific item type.
        if (array_key_exists('item_type_id', $params)) {
            $select->joinLeft(array('item_types_elements' => $db->ItemTypesElements),
                'item_types_elements.element_id = elements.id', array());
            $select->where('item_types_elements.item_type_id = ? OR item_types_elements.item_type_id IS NULL', 
                (int)$params['item_type_id']);
        } else if (array_key_exists('exclude_item_type', $params)) {
            $select->where('element_sets.name != ?', ELEMENT_SET_ITEM_TYPE);
        }
    }
    
    /**
     * Override parent class method to retrieve a multidimensional array of 
     * elements, organized by element set, to be used in Zend's FormSelect view 
     * helper.
     * 
     * @param array $options Set of parameters for searching/filtering results.
     * @see Omeka_Db_Table::findPairsForSelectForm()
     * @return array
     */
    public function findPairsForSelectForm(array $options = array())
    {
        $db = $this->getDb();
        // For backwards-compatibility.
        if (!array_key_exists('record_types', $options)) {
            $options['record_types'] = array('Item', 'All');
        }
        $select = $this->getSelectForFindBy($options);
        $select->reset(Zend_Db_Select::COLUMNS);
        $select->from(array(), array(
            'id' => 'elements.id', 
            'name' => 'elements.name',
            'set_name' => 'element_sets.name',
        ));

        $elements = $this->fetchAll($select);
        $options = array();
        foreach ($elements as $element) {
            $options[__($element['set_name'])][$element['id']] = __($element['name']);
        }
        return $options;
    }
}
