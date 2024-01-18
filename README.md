********************************************************************************
# REDCap External Module: Rename File Upload

Luke Stevens, Murdoch Children's Research Institute https://www.mcri.edu.au

[https://github.com/lsgs/redcap-rename-file-upload](https://github.com/lsgs/redcap-rename-file-upload)
********************************************************************************
## Summary

Tag file fields with the action tag `@RENAME-UPLOAD='name_pattern'` to specify how files uploaded to the field should be renamed.

Notes:
* The tag is ignored for fields other than file upload fields.
* Piping of field values and smart variables into the action tag *is* supported, and can utilise data entered onto the same form as the file upload field because the renaming occurs as the form is saved.
* Do not include any file extension within the pattern. Renamed files will keep the extension with which they were uploaded.

********************************************************************************
## Examples

* `@RENAME-UPLOAD='MyCustomFilenamePattern_rec-[record-name]'`
* `@RENAME-UPLOAD='MyCustomFilenamePattern_[project-id]-[record-name]-[filename_part]'`
* `@RENAME-UPLOAD='MyCustomFilenamePattern_[record-name]-[survey-time-completed]'`
********************************************************************************