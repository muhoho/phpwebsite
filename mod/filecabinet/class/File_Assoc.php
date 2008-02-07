<?php
/**
 * @version $Id$
 * @author Matthew McNaney <mcnaney at gmail dot com>
 */

class FC_File_Assoc {
    var $id         = 0;
    var $file_type  = 0;
    var $file_id    = 0;
    var $resize     = null;
    var $_use_style = true;
    /**
     * If the file assoc is an image and no_link is true,
     * the image's default link (if any) will be supressed
     */
    var $_link_image = true;
    var $_allow_caption = true;

    function FC_File_Assoc($id=0)
    {
        if (!$id) {
            return;
        }

        $this->id = (int)$id;
        $db = new PHPWS_DB('fc_file_assoc');
        $result = $db->loadObject($this);
        if (!PHPWS_Error::logIfError($result)) {
            if (!$result) {
                $this->id = 0;
            }
        }
    }

    function getSource()
    {
        switch ($this->file_type) {
        case FC_IMAGE:
        case FC_IMAGE_RESIZE:
            PHPWS_Core::initModClass('filecabinet', 'Image.php');
            $image = new PHPWS_Image($this->file_id);
            return $image;

        case FC_DOCUMENT:
            PHPWS_Core::initModClass('filecabinet', 'Document.php');
            $document = new PHPWS_Document($this->file_id);
            return $document;

        case FC_MEDIA:
            PHPWS_Core::initModClass('filecabinet', 'Multimedia.php');
            $media = new PHPWS_Multimedia($this->file_id);
            return $media;

        default:
            return null;
        }
    }

    function allowImageLink($link=true)
    {
        $this->_link_image = (bool)$link;
    }

    function isImage($include_resize=true)
    {
        if ($include_resize) {
            return ($this->file_type == FC_IMAGE || $this->file_type == FC_IMAGE_RESIZE);
        } else {
            return ($this->file_type == FC_IMAGE);
        }
    }

    function isDocument()
    {
        return ($this->file_type == FC_DOCUMENT);
    }

    function isMedia()
    {
        return ($this->file_type == FC_MEDIA);
    }

    function isResize()
    {
        return ($this->file_type == FC_IMAGE_RESIZE);
    }

    function allowCaption($allow=true)
    {
        $this->_allow_caption = (bool)$allow;
    }

    function deadAssoc()
    {
        $this->delete();
        $this->id        = 0;
        $this->file_type = 0;
        $this->file_id   = 0;
        $this->resize    = null;
    }

    function getFolderType()
    {
        switch ($this->file_type) {
        case FC_IMAGE:
        case FC_IMAGE_FOLDER:
        case FC_IMAGE_RANDOM:
        case FC_IMAGE_RESIZE:
            return IMAGE_FOLDER;

        case FC_DOCUMENT:
        case FC_DOCUMENT_FOLDER:
            return DOCUMENT_FOLDER;

        case FC_MEDIA:
            return MULTIMEDIA_FOLDER;
        }
    }


    function getTag($embed=false)
    {
        PHPWS_Core::initModClass('filecabinet', 'Multimedia.php');
        PHPWS_Core::initModClass('filecabinet', 'Image.php');
        PHPWS_Core::initModClass('filecabinet', 'Document.php');

        if ($this->_use_style) {
            Layout::addStyle('filecabinet', 'file_view.css');
        }

        switch ($this->file_type) {
        case FC_IMAGE:
            $image = new PHPWS_Image($this->file_id);
            if ($image->id) {
                if (PHPWS_Settings::get('filecabinet', 'caption_images') && $this->_allow_caption) {
                    return $image->captioned(null, !$this->_link_image);
                } else {
                    return $image->getTag(null, !$this->_link_image);
                }
            } else {
                $this->deadAssoc();
            }
            break;

        case FC_IMAGE_RESIZE:
            return $this->getResize();

        case FC_IMAGE_FOLDER:
            return $this->slideshow();

        case FC_IMAGE_RANDOM:
            return $this->randomImage();

        case FC_DOCUMENT:
            $document = new PHPWS_Document($this->file_id);
            if ($document->id) {
                return $document->getTag($embed);
            } else {
                $this->deadAssoc();
            }
            break;

        case FC_DOCUMENT_FOLDER:
            return $this->documentFolder();

        case FC_MEDIA:
            $media = new PHPWS_Multimedia($this->file_id);
            if ($media->id) {
                return $media->getTag($embed);
            } else {
                $this->deadAssoc();
            }
            break;
        }
        return null;
    }

    function getResize($link_parent=false)
    {
        PHPWS_Core::initModClass('filecabinet', 'Image.php');
        $source = new PHPWS_Image($this->file_id);
        $resize = clone($source);
        $resize->file_directory = $source->getResizePath();
        $resize->file_name = $this->resize;
        $resize->loadDimensions();
        if ($link_parent) {
            return $source->getJSView(false, $resize->getTag(null, false));
        } else {
            return $resize->getTag(null, $this->_link_image);
        }
    }

    function documentFolder()
    {
        $folder = new Folder($this->file_id);
        $folder->loadFiles();
        foreach ($folder->_files as $document) {
            $tpl['files'][] = array('TITLE'=>$document->getViewLink(true), 'SIZE'=>$document->getSize(true));
        }
        $tpl['ICON'] = '<img src="images/mod/filecabinet/file_manager/folder_contents.png" />';
        $tpl['DOWNLOAD'] = sprintf(dgettext('filecabinet', 'Download from %s'), $folder->title);
        return PHPWS_Template::process($tpl, 'filecabinet', 'document_download.tpl');

    }

    function randomImage()
    {
        PHPWS_Core::initModClass('filecabinet', 'Image.php');
        $image = new PHPWS_Image;
        $db = new PHPWS_DB('images');
        $db->addWhere('folder_id', $this->file_id);
        $db->addorder('random');
        $db->setLimit(1);
        if ($db->loadObject($image)) {
            return $image->getTag();
        } else {
            return dgettext('filecabinet', 'Folder missing image files.');
        }
    }

    function slideshow()
    {
        Layout::addStyle('filecabinet', 'style.css');
        PHPWS_Core::initModClass('filecabinet', 'Image.php');
        $db = new PHPWS_DB('images');
        $db->addWhere('folder_id', $this->file_id);

        $result = $db->getObjects('PHPWS_Image');
        if (PHPWS_Error::logIfError($result) || !$result) {
            return dgettext('filecabinet', 'Folder missing image files.');
        } else {
            foreach ($result as $image) {
                $tpl['thumbnails'][] = array('IMAGE'=> $image->getJSView(true));
            }
            return PHPWS_Template::process($tpl, 'filecabinet', 'ss_box.tpl');
        }
    }

    function getTable()
    {
        switch ($this->file_type) {
        case FC_IMAGE:
        case FC_IMAGE_FOLDER:
        case FC_IMAGE_RANDOM:
        case FC_IMAGE_RESIZE:
            return 'images';

        case FC_DOCUMENT:
        case FC_DOCUMENT_FOLDER:
            return 'documents';

        case FC_MEDIA:
            return 'multimedia';
        }

    }

    function getFolder()
    {
        $db = new PHPWS_DB('folders');
        if ($this->file_type == FC_IMAGE_RANDOM || $this->file_type == FC_IMAGE_FOLDER
            || $this->file_type == FC_DOCUMENT_FOLDER) {
            $folder = new Folder($this->file_id);
            if (PHPWS_Error::logIfError($folder) || !$folder->id) {
                return false;
            } else {
                return $folder;
            }
        } else {
            $table = $this->getTable();
            $folder = new Folder;
            $db->addWhere('fc_file_assoc.id', $this->id);
            $db->addWhere('fc_file_assoc.file_id', "$table.id");
            $db->addWhere('folders.id', "$table.folder_id");

            $result = $db->loadObject($folder);
            if (PHPWS_Error::logIfError($result) || !$result) {
                return false;
            } else {
                return $folder;
            }
        }
    }

    function save()
    {
        $db = new PHPWS_DB('fc_file_assoc');
        return $db->saveObject($this);
    }

    function updateTag($file_type, $id, $tag)
    {
        $db = new PHPWS_DB('fc_file_assoc');
        $db->addWhere('ftype', (int)$file_type);
        $db->addWhere('file_id', (int)$id);
        $db->addValue('tag',  htmlentities($tag, ENT_QUOTES, 'UTF-8'));
        $db->update();
    }

    function imageFolderView()
    {
        PHPWS_Core::initModClass('filecabinet', 'Image.php');
        $db = new PHPWS_DB('images');
        $db->addWhere('folder_id', $this->file_id);
        $result = $db->getObjects('PHPWS_Image');
        test($result,1);
    }

    function delete()
    {
        $db = new PHPWS_DB('fc_file_assoc');
        $db->addWhere('id', $this->id);
        return $db->delete();
    }
}

?>