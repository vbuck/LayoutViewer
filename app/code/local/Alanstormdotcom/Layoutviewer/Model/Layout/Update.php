<?php

/*
* The MIT License (MIT)
* 
* Copyright (c) 2010 - 2013 Pulse Storm LLC
* 
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
* 
* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.
* 
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*/

class Alanstormdotcom_Layoutviewer_Model_Layout_Update 
    extends Mage_Core_Model_Layout_Update
{

    //we'll display this request's package layout
    
    //we'll also display this request's "reduced" layout, etc. etc

    /**
     * Collect and merge layout updates from file
     *
     * @param string        $area     The system area.
     * @param string        $package  The design package name.
     * @param string        $theme    The package theme name.
     * @param integer|null  $storeId  An optional store ID for context.
     * 
     * @return Mage_Core_Model_Layout_Element
     */
    public function getFileLayoutUpdatesXml($area, $package, $theme, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = Mage::app()->getStore()->getId();
        }
        /* @var $design Mage_Core_Model_Design_Package */
        $design = Mage::getSingleton('core/design_package');
        $layoutXml = null;
        $elementClass = $this->getElementClass();
        $updatesRoot = Mage::app()->getConfig()->getNode($area.'/layout/updates');
        Mage::dispatchEvent('core_layout_update_updates_get_after', array('updates' => $updatesRoot));

        /*** Start version compatibility updates ***/
        $version = Mage::getVersionInfo();

        // Support for CE 1.9 changes for theme fallback
        if ($version['major'] == 1 && $version['minor'] >= 9) {
            $updates        = $updatesRoot->asArray();
            $themeUpdates   = Mage::getSingleton('core/design_config')->getNode("$area/$package/$theme/layout/updates");

            if ($themeUpdates && is_array($themeUpdates->asArray())) {
                //array_values() to ensure that theme-specific layouts don't override, but add to module layouts
                $updates = array_merge($updates, array_values($themeUpdates->asArray()));
            }

            $updateFiles = array();

            foreach ($updates as $updateNode) {
                if (!empty($updateNode['file'])) {
                    $module = isset($updateNode['@']['module']) ? $updateNode['@']['module'] : false;

                    if ($module && Mage::getStoreConfigFlag('advanced/modules_disable_output/' . $module, $storeId)) {
                        continue;
                    }

                    $updateFiles[] = $updateNode['file'];
                }
            }
        } else { // All other versions (presumably < 1.9 but > 1.3)
            $updateFiles = array();

            foreach ($updatesRoot->children() as $updateNode) {
                if ($updateNode->file) {
                    $module = $updateNode->getAttribute('module');

                    if ($module && Mage::getStoreConfigFlag('advanced/modules_disable_output/' . $module, $storeId)) {
                        continue;
                    }

                    $updateFiles[] = (string)$updateNode->file;
                }
            }
        }
        /*** End version compatibility updates ***/

        // custom local layout updates file - load always last
        $updateFiles[] = 'local.xml';
        $layoutStr = '';
        foreach ($updateFiles as $file) {
            $filename = $design->getLayoutFilename($file, array(
                '_area'    => $area,
                '_package' => $package,
                '_theme'   => $theme
            ));
            if (!is_readable($filename)) {
                continue;
            }
            $fileStr = file_get_contents($filename);
            $fileStr = str_replace($this->_subst['from'], $this->_subst['to'], $fileStr);
            $fileXml = simplexml_load_string($fileStr, $elementClass);
            if (!$fileXml instanceof SimpleXMLElement) {
                continue;
            }

            /*** Start attribute injection ***/
            foreach ($fileXml->children() as $handle) {
                foreach ($handle->children() as $child) {
                    $child->addAttribute('x-layout-file', $file);
                }
            }
            /*** End attribute injection ***/

            $layoutStr .= $fileXml->innerXml();
        }
        $layoutXml = simplexml_load_string('<layouts>'.$layoutStr.'</layouts>', $elementClass);
        return $layoutXml;
    }

    public function getPackageLayout()
    {
        $this->fetchFileLayoutUpdates();
        return $this->_packageLayout;
    }

}