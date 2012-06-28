CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Installation
 * Definitions
 * Usage
 * Design Decisions


INTRODUCTION
------------

The configuration management module enables the ability to keep track of
specific configurations on a Drupal site, provides the ability to move these
configurations between different environments (local, dev, qa, prod), and also
move configurations between completely different sites (migrate configurations)

This module is a rewrite of the features module without the features part of it.
The module also uses some concepts from the configuration management initiative,
specifically the concept of the "activestore" and "datastore" architecture.

The configuration module isn't a replacement for features. The vision is that 
they could work together. Features currently serves two purposes, 1) to group
configuration together to satisfy a certain use-case, and 2) the actual export
and management of configuration into a "feature" module. Features module is an
awesome module when using it for what it was built for, creating KIT compliant
features. The reality is most people that use features probably haven't even
read the KIT specification.


INSTALLATION
------------

After downloading the configuration module and uncompressing it, copy the whole 
configuration directory to your modules directory and activate the module.

DEFINITIONS
-----------
                                   
 * activestore
   
   The activestore is where configuration is read from by Drupal. The
   configuration module imports all exportables into the database.  All
   exportables effectively become faux-exportables, configuration is not read 
   from exported code.
   
 * datastore
 
   The datastore is configuration data stored on-disk using ctools exportable 
   files and configuration api exports.  This configuration is not read and
   accessed by Drupal for page construction. Configuration from the datastore
   is activated (imported) into the activestore. Drupal accesses configuration
   from the activestore.
   
 * config.export
 
   Documentation needs to be written.
 
 * migration
 
   Documentation needs to be written.
  

USAGE
-----

 * Tracking configuration
 
   Add documentation on how to track configuration.
 
 * Un-tracking configuration
 
   Add documentation on how to stop tracking configuration.
 
 * Migrate configuration to another site.
 
   Add documentation on how to migrate to another site.
 
 * General settings
 
   Describe what each setting does.


DESIGN DECISIONS
----------------
