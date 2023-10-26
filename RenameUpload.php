<?php

/**
 * REDCap External Module: Rename Upload
 * @author Luke Stevens, Murdoch Children's Research Institute
 */

namespace MCRI\RenameUpload;

use ExternalModules\AbstractExternalModule;

class RenameUpload extends AbstractExternalModule
{
    const ACTION_TAG = '@RENAME-UPLOAD';
    const CONTAINER_CLASS = 'EM-RENAME-UPLOAD';
    protected $isSurvey = false;
    protected $record;
    protected $event_id;
    protected $instance;
    protected $instrument;
    protected $taggedFields = [];

    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)
    {
        $this->isSurvey = true;
        $this->record = $record;
        $this->instrument = $instrument;
        $this->event_id = $event_id;
        $this->instance = $repeat_instance;

        global $Proj;

        $ff = false;
        foreach (array_keys($Proj->forms[$instrument]['fields']) as $f) {
            if ($Proj->metadata[$f]['element_type'] === 'file') {
                $ff = true;
                $string = $Proj->metadata[$f]['misc'];
                $searchString = self::ACTION_TAG;
                if (strpos($string, $searchString) !== false) {
                    $this->mapFieldKeyGetDocId($project_id, $string, $Proj->metadata[$f]);
                }
            }
        }
        $this->pageTop();
    }

    // Function to map and check the field Name
    public function mapFieldKeyGetDocId($project_id, $string, $meta_data,)
    {
        $record_name = $meta_data['field_name'];
        $formData = $_POST;
        if (array_key_exists($record_name, $formData)) {
            $doc_id = $formData[$record_name];
            if ($doc_id) {
                $this->changeFileName($project_id, $string, $meta_data, $doc_id);
            }
        }
    }

    //function to change the file name in DB using doc_id
    public function changeFileName($project_id, $string, $meta_data, $doc_id)
    {
        $record_name = $meta_data['field_name'];
        $parts = explode('=', $string);
        $rename_substr = '';
        $survey_time = date("Y-m-d-h:i:s");
        $pattern_One = '/\[project-id\]-\[record-name\]/';
        $pattern_two = '/\[record-name\]-\[survey-time-completed\]/';
        if (count($parts) === 2) {
            $rename_substr = trim($parts[1], "'");
        }
        $rename_file_name = '';
        if (preg_match($pattern_One, $string)) {
            $replacements = [
                '[project-id]' => $project_id,
                '[record-name]' => $record_name,
            ];
            $rename_file_name = str_replace(array_keys($replacements), $replacements, $rename_substr);
        } elseif (preg_match($pattern_two, $string)) {
            $replacements = [
                '[record-name]' => $record_name,
                '[survey-time-completed]' => $survey_time,
            ];
            $rename_file_name = str_replace(array_keys($replacements), $replacements, $rename_substr);
        } elseif (preg_match("/='(.*?)'/", $string, $matches)) {
            $rename_file_name = $matches[1];
        }

        if ($rename_file_name !== '') {
            $query = "SELECT doc_name FROM `redcap_edocs_metadata` WHERE project_id = $project_id and doc_id = $doc_id ORDER BY doc_id DESC LIMIT 1;";
            $getdoc_details = db_query($query);
            $old_file_name = '';

            while ($row = db_fetch_array($getdoc_details)) {
                if ($row['doc_name']) {
                    $old_file_name = $row['doc_name'];
                }
            }
            $old_file_name_parts = explode('.', $old_file_name);
            $file_extension = end($old_file_name_parts);
            $new_file_name = $rename_file_name . "." . $file_extension;
            $update_query = "UPDATE `redcap_edocs_metadata` SET`doc_name`='$new_file_name' WHERE project_id = $project_id and doc_id = $doc_id;";
            db_query($update_query);
        }
        return true;
    }

    protected function pageTop()
    {
        $this->setTaggedFields();
        if (is_array($this->taggedFields) && count($this->taggedFields) === 0) return;
    }

    public function setTaggedFields()
    {
        $this->taggedFields = array();

        $instrumentFields = \REDCap::getDataDictionary('array', false, true, $this->instrument);

        if ($this->isSurvey && isset($_GET['__page__'])) {
            global $pageFields;
            $thisPageFields = array();
            foreach ($pageFields[$_GET['__page__']] as $pf) {
                $thisPageFields[$pf] = $instrumentFields[$pf];
            }
        } else {
            $thisPageFields = $instrumentFields;
        }

        foreach ($thisPageFields as $fieldName => $fieldDetails) {
            if (!in_array($fieldDetails['field_type'], ['file'])) continue;
            $fieldAnnotation = \Piping::replaceVariablesInLabel(
                $fieldDetails['field_annotation'], // $label='', 
                $this->record, // $record=null, 
                $this->event_id, // $event_id=null, 
                $this->instance, // $instance=1, 
                array(), // $record_data=array(),
                true, // $replaceWithUnderlineIfMissing=true, 
                null, // $project_id=null, 
                false // $wrapValueInSpan=true
            );

            // @RENAME UPLOAD=?
            $pattern_One = '/\[project-id\]-\[record-name\]/';
            $pattern_two = '/\[record-name\]-\[survey-time-completed\]/';
            $matches = array();

            if (preg_match($pattern_One, $fieldAnnotation, $matches)) {
                $cols = (array_key_exists(1, $matches)) ? intval($matches[1]) : 0;
                if ($cols > 0) $this->taggedFields[] = "$fieldName:$cols";
            }
            if (preg_match($pattern_two, $fieldAnnotation, $matches)) {
                $cols = (array_key_exists(1, $matches)) ? intval($matches[1]) : 0;
                if ($cols > 0) $this->taggedFields[] = "$fieldName:$cols";
            }
        }
    }
}