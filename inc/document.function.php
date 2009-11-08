<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2009 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Julien Dombre
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')){
   die("Sorry. You can't access directly to this file");
}

/**
 * Move a file to a new location
 * Work even if dest file already exists
 *
 * @param $srce source file path
 * @param $dest destination file path
 *
 * @return boolean : success
 */
function renameForce ($srce, $dest) {

   // File already present
   if (is_file($dest)) {
      // As content is the same (sha1sum), no need to copy
      @unlink($srce);
      return true;
   }
   // Move
   return rename($srce,$dest);
}

/**
 * Move an uploadd document (files in GLPI_DOC_DIR."/_uploads" dir)
 *
 * @param $filename filename to move
 * @param $old_file old file name to replace : to unlink it
 *
 * @return boolean for success
 *
 **/
function moveUploadedDocument(&$input,$filename) {
   global $CFG_GLPI,$LANG;

   $fullpath = GLPI_DOC_DIR."/_uploads/".$filename;

   if (!is_dir(GLPI_DOC_DIR."/_uploads")) {
      addMessageAfterRedirect($LANG['document'][35], false, ERROR);
      return false;
   }
   if (!is_file($fullpath)) {
      addMessageAfterRedirect($LANG['document'][38]."&nbsp;: ".$fullpath, false, ERROR);
      return false;
   }
   $sha1sum = sha1_file($fullpath);
   $dir = isValidDoc($filename);
   $new_path = getUploadFileValidLocationName($dir, $sha1sum);

   if (!$sha1sum || !$dir || !$new_path) {
      return false;
   }

   // Delete old file (if not used by another doc)
   if (isset($input['current_filename'])
       && !empty($input['current_filename'])
       && is_file(GLPI_DOC_DIR."/".$input['current_filename'])
       && countElementsInTable('glpi_documents',"`sha1sum`='$sha1sum'")<=1) {
      if (unlink(GLPI_DOC_DIR."/".$input['current_filename'])) {
         addMessageAfterRedirect($LANG['document'][24]." ".GLPI_DOC_DIR."/".$input['current_filename']);
      } else {
         addMessageAfterRedirect($LANG['document'][25]." ".GLPI_DOC_DIR."/".$input['current_filename'],
                                 false,ERROR);
      }
   }

   // Local file : try to detect mime type
   if (function_exists('finfo_open') && $finfo = finfo_open(FILEINFO_MIME)) {
      $input['mime'] = finfo_file($finfo, $fullpath);
      finfo_close($finfo);
   } else if (function_exists('mime_content_type')) {
      $input['mime'] = mime_content_type($fullpath);
   }

   if (is_writable(GLPI_DOC_DIR."/_uploads/") && is_writable ($fullpath)) { // Move if allowed
      if (renameForce($fullpath, GLPI_DOC_DIR."/".$new_path)) {
         addMessageAfterRedirect($LANG['document'][39]);
      } else {
         addMessageAfterRedirect($LANG['document'][40],false,ERROR);
         return false;
      }
   } else { // Copy (will overwrite dest file is present)
      if (copy($fullpath, GLPI_DOC_DIR."/".$new_path)) {
         addMessageAfterRedirect($LANG['document'][41]);
      } else {
         addMessageAfterRedirect($LANG['document'][40],false,ERROR);
         return false;
      }
   }

   // For display
   $input['filename'] = addslashes($filename);
   // Storage path
   $input['filepath'] = $new_path;
   // Checksum
   $input['sha1sum'] = $sha1sum;
   return true;
}

/**
 * Upload a new file
 *
 * @param $input data need for add/update (will be completed)
 * @param $FILEDESC FILE descriptor
 *
 * @return true on success
 **/
function uploadDocument(&$input,$FILEDESC) {
   global $LANG;

   if (!count($FILEDESC) || empty($FILEDESC['name']) || !is_file($FILEDESC['tmp_name'])) {
      return false;
   }

   $sha1sum = sha1_file($FILEDESC['tmp_name']);
   $dir = isValidDoc($FILEDESC['name']);
   $path = getUploadFileValidLocationName($dir,$sha1sum);
   if (!$sha1sum || !$dir || !$path) {
      return false;
   }

   // Delete old file (if not used by another doc)
   if (isset($input['current_filename'])
       && !empty($input['current_filename'])
       && countElementsInTable('glpi_documents',"`sha1sum`='$sha1sum'")<=1) {
      if (unlink(GLPI_DOC_DIR."/".$input['current_filename'])) {
         addMessageAfterRedirect($LANG['document'][24]." ".GLPI_DOC_DIR."/".$input['current_filename']);
      } else {
         addMessageAfterRedirect($LANG['document'][25]." ".GLPI_DOC_DIR."/".$input['current_filename'],
                                 false,ERROR);
      }
   }

   // Mime type from client
   if (isset($FILEDESC['type'])&& !empty($FILEDESC['type'])) {
      $input['mime'] = $FILEDESC['type'];
   }

   // Move uploaded file
   if (renameForce($FILEDESC['tmp_name'], GLPI_DOC_DIR."/".$path)) {
      addMessageAfterRedirect($LANG['document'][26]);
      // For display
      $input['filename'] = addslashes($FILEDESC['name']);
      // Storage path
      $input['filepath'] = $path;
      // Checksum
      $input['sha1sum'] = $sha1sum;
      return true;
   }
   addMessageAfterRedirect($LANG['document'][27],false,ERROR);
   return false;
}

/**
 * Find a valid path for the new file
 *
 * @param $dir dir to search a free path for the file
 * @param $filename new filename

 * @return nothing
 **/
function getUploadFileValidLocationName($dir,$sha1sum) {
   global $CFG_GLPI,$LANG;

   if (empty($dir)) {
      addMessageAfterRedirect($LANG['document'][32],false,ERROR);
      return '';
   }
   if (!is_dir(GLPI_DOC_DIR)) {
      addMessageAfterRedirect($LANG['document'][31]." ".GLPI_DOC_DIR,false,ERROR);
      return '';
   }
   $subdir = $dir.'/'.substr($sha1sum,0,2);
   if (!is_dir(GLPI_DOC_DIR."/".$subdir) && @mkdir(GLPI_DOC_DIR."/".$subdir,0777,true)) {
      addMessageAfterRedirect($LANG['document'][34]." ".GLPI_DOC_DIR."/".$subdir);
   }
   if (!is_dir(GLPI_DOC_DIR."/".$subdir)) {
      addMessageAfterRedirect($LANG['document'][29]." ".GLPI_DOC_DIR."/".$subdir." ".
                              $LANG['document'][30],false,ERROR);
      return '';
   }
   return $subdir.'/'.substr($sha1sum,2).'.'.$dir;
}

/**
 * Show documents associated to an item
 *
 * @param $itemtype item type
 * @param $ID item ID
 * @param $withtemplate if 3 -> view via helpdesk -> no links
 **/
function showDocumentAssociated($itemtype,$ID,$withtemplate='') {
   global $DB, $CFG_GLPI, $LANG, $LINK_ID_TABLE;

   if ($itemtype!=KNOWBASE_TYPE) {
      if (!haveRight("document","r") || !haveTypeRight($itemtype,"r")) {
         return false;
      }
   }

   if (empty($withtemplate)) {
      $withtemplate=0;
   }
   $ci=new CommonItem();
   $ci->getFromDB($itemtype,$ID);
   $canread=$ci->obj->can($ID,'r');
   $canedit=$ci->obj->can($ID,'w');
   $is_recursive=0;
   if ($ci->getField('is_recursive')) {
      $is_recursive=1;
   }

   $query = "SELECT `glpi_documents_items`.`id` AS assocID, `glpi_entities`.`id` AS entity,
                    `glpi_documents`.`name` AS assocName, `glpi_documents`.*
             FROM `glpi_documents_items`
             LEFT JOIN `glpi_documents`
                       ON (`glpi_documents_items`.`documents_id`=`glpi_documents`.`id`)
             LEFT JOIN `glpi_entities` ON (`glpi_documents`.`entities_id`=`glpi_entities`.`id`)
             WHERE `glpi_documents_items`.`items_id` = '$ID'
                   AND `glpi_documents_items`.`itemtype` = '$itemtype' ";

   if (isset($_SESSION["glpiID"])) {
      $query .= getEntitiesRestrictRequest(" AND","glpi_documents",'','',true);
   } else {
      // Anonymous access from FAQ
      $query .= " AND `glpi_documents`.`entities_id`= '0' ";
   }
   // Document : search links in both order using union
   if ($itemtype==DOCUMENT_TYPE) {
      $query .= "UNION
                 SELECT `glpi_documents_items`.`id` AS assocID, `glpi_entities`.`id` AS entity,
                        `glpi_documents`.`name` AS assocName, `glpi_documents`.*
                 FROM `glpi_documents_items`
                 LEFT JOIN `glpi_documents`
                           ON (`glpi_documents_items`.`items_id`=`glpi_documents`.`id`)
                 LEFT JOIN `glpi_entities` ON (`glpi_documents`.`entities_id`=`glpi_entities`.`id`)
                 WHERE `glpi_documents_items`.`documents_id` = '$ID'
                       AND `glpi_documents_items`.`itemtype` = '$itemtype' ";

      if (isset($_SESSION["glpiID"])) {
         $query .= getEntitiesRestrictRequest(" AND","glpi_documents",'','',true);
      } else {
         // Anonymous access from FAQ
         $query .= " AND `glpi_documents`.`entities_id`='0' ";
      }
   }
   $query .= " ORDER BY `assocName`";

   $result = $DB->query($query);
   $number = $DB->numrows($result);
   $i = 0;

   if ($withtemplate!=2) {
      echo "<form method='post' action=\"".
             $CFG_GLPI["root_doc"]."/front/document.form.php\" enctype=\"multipart/form-data\">";
   }
   echo "<div class='center'><table class='tab_cadre_fixe'>";
   echo "<tr><th colspan='7'>".$LANG['document'][21]."&nbsp;:</th></tr>";
   echo "<tr><th>".$LANG['common'][16]."</th>";
   echo "<th>".$LANG['entity'][0]."</th>";
   echo "<th>".$LANG['document'][2]."</th>";
   echo "<th>".$LANG['document'][33]."</th>";
   echo "<th>".$LANG['document'][3]."</th>";
   echo "<th>".$LANG['document'][4]."</th>";
   if ($withtemplate<2) {
      echo "<th>&nbsp;</th>";
   }
   echo "</tr>";
   $used=array();
   if ($number) {
      // Don't use this for document associated to document
      // To not loose navigation list for current document
      if ($itemtype!=DOCUMENT_TYPE) {
         initNavigateListItems(DOCUMENT_TYPE,$ci->getType()." = ".$ci->getName());
      }

      $document = new Document();
      while ($data=$DB->fetch_assoc($result)) {
         $docID=$data["id"];
         if (!$document->getFromDB($docID)) {
            continue;
         }
         if ($itemtype!=DOCUMENT_TYPE) {
            addToNavigateListItems(DOCUMENT_TYPE,$docID);
         }
         $used[$docID]=$docID;
         $assocID=$data["assocID"];

         echo "<tr class='tab_bg_1".($data["is_deleted"]?"_2":"")."'>";
         echo "<td class='center'>".$document->getLink()."</td>";
         echo "<td class='center'>".getDropdownName("glpi_entities",$data['entity'])."</td>";
         echo "<td class='center'>".$document->getDownloadLink()."</td>";
         echo "<td class='center'>";
         if (!empty($data["link"])) {
            echo "<a target=_blank href='".formatOutputWebLink($data["link"])."'>".$data["link"]."</a>";
         } else {;
            echo "&nbsp;";
         }
         echo "</td>";
         echo "<td class='center'>".getDropdownName("glpi_documentscategories",
                                                    $data["documentscategories_id"])."</td>";
         echo "<td class='center'>".$data["mime"]."</td>";

         if ($withtemplate<2) {
            echo "<td class='tab_bg_2 center'>";
            if ($canedit) {
               echo "<a href='".
                      $CFG_GLPI["root_doc"]."/front/document.form.php?deletedocumentitem=1&amp;"
                      ."id=$assocID&amp;itemtype=$itemtype&amp;items_id=$ID&amp;documents_id=$docID'>
                      <strong>".$LANG['buttons'][6]."</strong></a>";
            } else {
               echo "&nbsp;";
            }
            echo "</td>";
         }
         echo "</tr>";
         $i++;
      }
   }

   if ($canedit) {
      // Restrict entity for knowbase
      $entities="";
      $entity=$_SESSION["glpiactive_entity"];
      if ($ci->obj->isEntityAssign()) {
         $entity=$ci->obj->getEntityID();
         if ($ci->obj->isRecursive()) {
            $entities = getSonsOf('glpi_entities',$entity);
         } else {
            $entities = $entity;
         }
      }
      $limit = getEntitiesRestrictRequest(" AND ","glpi_documents",'',$entities,true);
      $q="SELECT count(*)
          FROM `glpi_documents`
          WHERE `is_deleted`='0' $limit";

      $result = $DB->query($q);
      $nb = $DB->result($result,0,0);

      if ($withtemplate<2) {
         echo "<tr class='tab_bg_1'><td class='center' colspan='3'>";
         echo "<input type='hidden' name='entities_id' value='$entity'>";
         echo "<input type='hidden' name='is_recursive' value='$is_recursive'>";
         echo "<input type='hidden' name='itemtype' value='$itemtype'>";
         echo "<input type='hidden' name='items_id' value='$ID'>";
         echo "<input type='file' name='filename' size='25'>&nbsp;&nbsp;";
         echo "<input type='submit' name='add' value=\"".$LANG['buttons'][8]."\" class='submit'></td>";

         if ($itemtype==DOCUMENT_TYPE) {
            $used[$ID]=$ID;
         }

         if ($nb>count($used)) {
            echo "<td class='left' colspan='2'>";
            echo "<div class='software-instal'>";
            dropdownDocument("documents_id",$entities,$used);
            echo "</div></td><td class='center'>";
            echo "<input type='submit' name='adddocumentitem' value=\"".
                   $LANG['buttons'][8]."\" class='submit'>";
            echo "</td><td>&nbsp;</td>";
         } else {
            echo "<td colspan='4'>&nbsp;</td>";
         }
         echo "</tr>";
      }
   }
   echo "</table></div>"    ;
   echo "</form>";
}

/**
 * Show dropdown of uploaded files
 *
 * @param $myname dropdown name
 **/
function showUploadedFilesDropdown($myname) {
   global $CFG_GLPI,$LANG;

   if (is_dir(GLPI_DOC_DIR."/_uploads")) {
      $uploaded_files=array();
      if ($handle = opendir(GLPI_DOC_DIR."/_uploads")) {
         while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
               $dir=isValidDoc($file);
               if (!empty($dir)) {
                  $uploaded_files[]=$file;
               }
            }
         }
         closedir($handle);
      }

      if (count($uploaded_files)) {
         echo "<select name='$myname'>";
         echo "<option value=''>-----</option>";
         foreach ($uploaded_files as $key => $val) {
            echo "<option value=\"$val\">$val</option>";
         }
         echo "</select>";
      } else {
         echo $LANG['document'][37];
      }
   } else {
      echo $LANG['document'][35];
   }
}

/**
 * Is this file a valid file ? check based on file extension
 *
 * @param $filename filename to clean
 **/
function isValidDoc($filename) {
   global $DB;

   $splitter=explode(".",$filename);
   $ext=end($splitter);

   $query="SELECT *
           FROM `glpi_documentstypes`
           WHERE `ext` LIKE '$ext'
                 AND `is_uploadable`='1'";
   if ($result = $DB->query($query)) {
      if ($DB->numrows($result)>0) {
         return utf8_strtoupper($ext);
      }
   }
   return "";
}

?>
