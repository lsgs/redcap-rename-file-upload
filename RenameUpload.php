<?php

/**
 * REDCap External Module: Rename Upload
 * @author Luke Stevens, Murdoch Children's Research Institute
 * @author Harneet Bhinder, Murdoch Children's Research Institute
 */

namespace MCRI\RenameUpload;

use ExternalModules\AbstractExternalModule;

use REDCap;

class RenameUpload extends AbstractExternalModule
{
    const ACTION_TAG = '@RENAME-UPLOAD';
    protected $project;
    protected $taggedFields = [];

    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)
    {
        // is one of the saved fields ($_POST) a file upload with the EM tag and a docid value?
        $instrumentFields = REDCap::getDataDictionary('array', false, true, $instrument);
        foreach ($_POST as $key => $value) {
            if (array_key_exists($key, $instrumentFields)) {
                $value = intval($value);
                $fieldType = $instrumentFields[$key]['field_type']; 
                $fieldAnnotation = $instrumentFields[$key]['field_annotation']; 

                if (empty($value)) continue;
                if ($fieldType!=='file') continue;

                // @RENAME_UPLOAD='asdf'
                $matches = array();
                if (preg_match("/".self::ACTION_TAG."\s*=\s*'(.+)'/", $fieldAnnotation, $matches)) {
                    $this->taggedFields[$key] = array(
                        'doc_id' => $value,
                        'pattern' => $matches[1]
                    );
                }
            }
        }

        if (count($this->taggedFields)===0) return;

        // for each tagged field with a file, get the old file name and rename if needed
        foreach ($this->taggedFields as $thisField => $fieldProp) {
            $new_file_name = \Piping::replaceVariablesInLabel(
                $fieldProp['pattern'], // $label='', 
                $record, // $record=null, 
                $event_id, // $event_id=null, 
                $repeat_instance, // $instance=1, 
                array(), // $record_data=array(),
                true, // $replaceWithUnderlineIfMissing=true, 
                null, // $project_id=null, 
                false // $wrapValueInSpan=true
            );

            $new_file_name = preg_replace('/[^A-Za-z0-9\-\_]/', '', $new_file_name); // ensure no invalid chars for file names

            $query = "SELECT doc_name FROM `redcap_edocs_metadata` WHERE project_id = ? and doc_id = ? ORDER BY doc_id DESC LIMIT 1;";
            $getdoc_details = $this->query($query, [$project_id, $fieldProp['doc_id']]);
            $old_file_name = '';

            while ($row = db_fetch_array($getdoc_details)) {
                if ($row['doc_name']) {
                    $old_file_name = $row['doc_name'];
                }
            }
            $old_file_name_parts = explode('.', $old_file_name);
            $file_extension = end($old_file_name_parts);
            $new_file_name = $new_file_name . "." . $file_extension;
            if ($old_file_name !== $new_file_name) {
                $update_query = "UPDATE `redcap_edocs_metadata` SET`doc_name`=? WHERE project_id = ? and doc_id = ? limit 1";
                $this->query($update_query, [$new_file_name, $project_id, $fieldProp['doc_id']]);
                $log_data = "Project Id = $project_id, \n Record Id = $record, \n , Doc Id = {$fieldProp['doc_id']} \n Old file name = $old_file_name, \n New file name = $new_file_name \n";
                REDCap::logEvent("Rename Upload External Module",  $log_data, "", $record, $event_id);
            }
        }
    }
}