<?php

/**
 * This class is for all files that are not images
 *
 * At some time there may be special circumstances for documents but
 * for now they are just download links.
 *
 * @author Matthew McNaney <matt at tux dot appstate dot edu>
 * @version $Id$
 */

PHPWS_Core::initModClass('filecabinet', 'File_Common.php');

class PHPWS_Document extends File_Common {
    var $_max_size        = MAX_DOCUMENT_SIZE;
    var $_classtype       = 'document';


    function PHPWS_Document($id=NULL)
    {
        $this->loadAllowedTypes();

        if (empty($id)) {
            return;
        }
        
        $this->setId((int)$id);

        $result = $this->init();
        if (PEAR::isError($result)) {
            $this->id = 0;
            $this->_errors[] = $result;
        } elseif (empty($result)) {
            $this->id = 0;
            $this->_errors[] = PHPWS_Error::get(FC_DOCUMENT_NOT_FOUND, 'filecabinet', 'PHPWS_Document');
        }

    }

    function loadAllowedTypes()
    {
        $this->_allowed_types = unserialize(ALLOWED_DOCUMENT_TYPES);
    }

    function getDefaultDirectory()
    {
        return PHPWS_Settings::get('filecabinet', 'base_doc_directory');
    }

    function init()
    {
        if (!isset($this->id)) {
            return FALSE;
        }

        $db = new PHPWS_DB('documents');
        return $db->loadObject($this);
    }

    function getIconView()
    {
        static $icon_list =NULL;

        if (empty($icon_list)) {
            $file = PHPWS_Core::getConfigFile('filecabinet', 'icons.php');
            if (!$file) {
                return '<img src="./images/mod/filecabinet/icons/document.png" />';
            } else {
                include $file;
            }
        }

        if (!@$graphic = $icon_list[$this->ext]) {
            return '<img src="./images/mod/filecabinet/icons/document.png" />';
        } else {
            return sprintf('<img src="./images/mod/filecabinet/icons/%s" />', $graphic);
        }
    }

    
    function save($no_dupes=TRUE, $write=TRUE)
    {
        if (empty($this->file_directory)) {
            return FALSE;
        }

        if (empty($this->title)) {
            $this->title = $this->file_name;
        }

        if ($write) {
            $result = $this->write();
            if (PEAR::isError($result)) {
                return $result;
            }
        }

        $db = new PHPWS_DB('documents');

        if ((bool)$no_dupes && empty($this->id)) {
            $db->addWhere('file_name',  $this->file_name);
            $db->addWhere('file_directory', $this->file_directory);
            $db->addColumn('id');
            $result = $db->select('one');
            if (PEAR::isError($result)) {
                return $result;
            } elseif (isset($result) && is_numeric($result)) {
                $this->id = $result;
                return TRUE;
            }

            $db->reset();
        }

        $result = $db->saveObject($this);

        if (PEAR::isError($result)) {
            return $result;
        }

        $key = $this->saveKey();
        if (PEAR::isError($key)) {
            return $key;
        }

        if ($this->_move_directory) {
            if (!$this->move_file($this->_move_directory . $this->file_name,
                                  $this->file_directory . $this->file_name)) {
                return PHPWS_Error::get(FC_FILE_MOVE, 'filecabinet', 'PHPWS_Image::save', $this->file_directory);
            }
        }

        if (!$this->key_id) {
            $this->key_id = $key->id;
            return $db->saveObject($this);
        }

        return TRUE;
    }

    function &saveKey()
    {
        if (empty($this->key_id)) {
            $key = new Key;
        } else {
            $key = new Key($this->key_id);
            if (PEAR::isError($key->_error)) {
                $key = new Key;
            }
        }

        $key->setModule('filecabinet');
        $key->setItemName('document');
        $key->setItemId($this->id);
        $key->setEditPermission('edit_document');
        $key->setUrl($this->getViewLink());
        $key->setTitle($this->title);
        $key->setSummary($this->description);

        $result = $key->save();

        return $key;
    }

    function delete()
    {
        if (!$this->id) {
            return FALSE;
        }

        $file_dir = $this->file_directory . $this->file_name;

        // if the file is not there, we want to continue anyway
        if (is_file($file_dir)) {
            if (!@unlink($file_dir)) {
                return PHPWS_Error::get(FC_COULD_NOT_DELETE, 'filecabinet', 'Document::delete', $file_dir);
            }
        }

        $db = new PHPWS_DB('documents');
        $db->addWhere('id', $this->id);
        return $db->delete();
    }

    function getRowTags()
    {
        translate('filecabinet');
        $vars['document_id'] = $this->id;

        if (javascriptEnabled()) {
            $js['address'] = sprintf('index.php?module=filecabinet&action=document_edit&document_id=%s&authkey=%s', $this->id, Current_User::getAuthkey());
            $js['label'] = _('Edit');
            $js['width'] = 550;
            $js['height'] = 430;
            $links[] = javascript('open_window', $js);
        } else {
            $vars['action'] = 'admin_edit_document';
            $links[] = PHPWS_Text::secureLink(_('Edit'), 'filecabinet', $vars);
        }

        $links[] = Current_User::popupPermission($this->key_id);

        $vars['action'] = 'clip_document';
        $links[] = PHPWS_Text::moduleLink(_('Clip'), 'filecabinet', $vars);

        $vars['action'] = 'delete_document';
        $js['QUESTION'] = _('Are you sure you want to delete this document?');
        $js['LINK'] = _('Delete');
        $js['ADDRESS'] = PHPWS_Text::linkAddress('filecabinet', $vars, TRUE);
        $links[] = javascript('confirm', $js);

        $tpl['FILE_NAME'] = $this->getViewLink(TRUE, TRUE);

        $tpl['ACTION'] = implode(' | ', $links);
        $tpl['SIZE'] = $this->getSize(TRUE);
        translate();
        return $tpl;
    }

    /**
     * Returns the full url that allows you to download the file
     */
    function getUrl()
    {
        return PHPWS_Core::getHomeHttp() . $this->getViewLink();
    }

    function getViewLink($format=FALSE, $use_filename=FALSE)
    {
        if (MOD_REWRITE_ENABLED) {
            $link = 'filecabinet/' . $this->id;
        } else {
            $link = sprintf('index.php?module=filecabinet&amp;id=' . $this->id);
        }

        if ($format) {
            if ($use_filename) {
                return sprintf('<a href="%s" title="%s">%s</a>', $link, $this->title, $this->file_name);
            } else {
                return sprintf('<a href="%s" title="%s">%s</a>', $link, $this->description, $this->title);
            }
        } else {
            return $link;
        }
    }
    
}

?>