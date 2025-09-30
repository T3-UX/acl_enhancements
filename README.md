# TYPO3 ACL Enhancements

## Overview
ACL Enhancements introduces **permission sets** for TYPO3: file-based, deployable,
and version-controlled access control configurations.

![ACL](./Resources/Public/Images/Screenshot.png?raw=true "ACL")

This is part of Q3 2025 ACL Budget Idea

Instead of storing backend group permissions only in the database, permission sets
make ACLs portable, editable, and transparent. They are stored as YAML files and can
be committed to version control, deployed through CI/CD pipelines, and debugged more easily.

## Features
- **Deployable ACLs**: manage backend permissions as code.
- **Import & export**: generate presets from existing backend groups or apply sets to groups.
- **Editor-friendly UI**: create, duplicate, preview, and debug permission sets in the TYPO3 backend.
- **Field-level insights**: see exactly which permission set controls a field.
- **Preview mode**: inspect non-editable sets, including which backend groups depend on them.
- **Extensibility**: support for custom file writers and extension-shipped ACL presets.

## Example Permission Set (YAML)
Below is an example of a deployable permission set (`All users`) defined as YAML:

```yaml
label: 'All users'
categories: { }
customOptions: { }
fileMounts: { }
filePermissions:
  - readFolder
  - writeFolder
  - addFolder
  - renameFolder
  - moveFolder
  - copyFolder
  - deleteFolder
  - readFile
  - writeFile
  - addFile
  - renameFile
  - moveFile
  - copyFile
  - deleteFile
languages: { }
mfaProviders: { }
modules:
  user_setup: true
resources:
  pages:
    permissions:
      - read
  sys_file:
    permissions:
      - read
  sys_file_collection:
    permissions:
      - read
  sys_file_metadata:
    permissions:
      - read
  sys_file_reference:
    permissions:
      - read
  tt_content:
    permissions:
      - read
    fields:
      - imageorient
      - table_caption
      - table_header_position
settings: { }
sites: { }
widgets: { }
workspaceLiveEditing: null
```

## Usage
1. Place permission set files in:
    - `Configuration/PermissionSets/` inside an extension, or
    - `config/permission-sets/` at project level.
2. Reference the set from your backend group.
3. Commit and deploy permission sets with your project code.

## Roadmap
Planned functionalities include:
- Comparing backend groups.
- Permission set inheritance and source tracking.
- Import from backend module uploads.
- Wizard for creating sets from existing presets.
- Automatic binding of groups to permission set files.