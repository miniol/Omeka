<?php
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2007-2010
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 */
 
/**
 * Sets the Content-Type header for all JSON-P requests.
 *
 * @package Omeka
 * @copyright Center for History and New Media, 2007-2010
 */
class Omeka_Controller_Plugin_Jsonp extends Zend_Controller_Plugin_Abstract
{
    /**
     * Callback parameter key.
     */
    const CALLBACK_KEY = 'callback';
    
    /**
     * Set the 'Content-Type' HTTP header to 'application/x-javascript' for
     * omeka-json requests.
     * 
     * @param Zend_Controller_Request_Abstract $request Request object.
     * @return void
     */
    public function postDispatch(Zend_Controller_Request_Abstract $request)
    {
        if ('omeka-json' == $request->getParam('output') 
            && $request->getParam(self::CALLBACK_KEY)) {
            $this->getResponse()->setHeader('Content-Type', 'application/x-javascript');
        }
    }
}

