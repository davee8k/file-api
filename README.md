# FileApi

## Description

Basic tools for interaction with files (create, upload, download, delete, etc.) for PHP

## Requirements

- PHP 7.1+ (PHP 5.4+ for version 0.86 and older)

## Usage

### FileApi class

Core functions for browsing, no create /edit/delete capabilities.

#### FileApiPhp class

Extension of FileApi with create/edit/delete capabilities.

#### FileApiFtp class

Alternative for FileApiPhp using FTP connection for file interactions.
(use only if php does not have edit rights)

### Utils class

Support function for filesystem interaction

- getMaxUpload - returns maximum possible size for file upload
- sizeToNum - converts text size format to numeric (1kB -> 1024)
- numToSize - converts numeric format to text (1024 -> 1kB)
- getExt - parse file extension
- getIcon - returns font awesome based on extension