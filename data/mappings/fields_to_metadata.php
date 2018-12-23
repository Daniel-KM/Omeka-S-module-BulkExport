<?php
/**
 * Mapping between common field name and standard Omeka metadata name from the
 * json-ld representation, or, in some cases, the value used inside the resource
 * form (mainly for media).
 *
 * This list can be completed or removed.
 */

return [
    'owner' => 'o:owner',
    'owner email' => 'o:email',
    'id' => 'o:id',
    'internal id' => 'o:id',
    'resource' => 'o:id',
    'resources' => 'o:id',
    'resource id' => 'o:id',
    'resource identifier' => 'dcterms:identifier',
    'record' => 'o:id',
    'records' => 'o:id',
    'record id' => 'o:id',
    'record identifier' => 'dcterms:identifier',
    'resource type' => 'resource_type',
    'record type' => 'resource_type',
    'resource template' => 'o:resource_template',
    'item type' => 'o:resource_class',
    'resource class' => 'o:resource_class',
    'visibility' => 'o:is_public',
    'public' => 'o:is_public',
    'item set' => 'o:item_set',
    'item sets' => 'o:item_set',
    'collection' => 'o:item_set',
    'collections' => 'o:item_set',
    'item set id' => 'o:item_set {o:id}',
    'collection id' => 'o:item_set {o:id}',
    'item set identifier' => 'o:item_set {dcterms:identifier}',
    'collection identifier' => 'o:item_set {dcterms:identifier}',
    'item set title' => 'o:item_set {dcterms:title}',
    'collection title' => 'o:item_set {dcterms:title}',
    'additions' => 'o:is_open',
    'open' => 'o:is_open',
    'openness' => 'o:is_open',
    'item' => 'o:item',
    'items' => 'o:item',
    'item id' => 'o:item {o:id}',
    'item identifier' => 'o:item {dcterms:identifier}',
    'media' => 'o:media',
    'media id' => 'o:media {o:id}',
    'media identifier' => 'o:media {dcterms:identifier}',
    'media title' => 'o:media {dcterms:title}',
    'media url' => 'url',
    'media html' => 'html',
    'html' => 'html',
    'iiif' => 'iiif',
    'iiif image' => 'iiif',
    'oembed' => 'oembed',
    'youtube' => 'youtube',
    'url' => 'url',
    'user' => 'o:user',
    'name' => 'o:name',
    'display name' => 'o:name',
    'username' => 'o:name',
    'user name' => 'o:name',
    'email' => 'o:email',
    'user email' => 'o:email',
    'role' => 'o:role',
    'user role' => 'o:role',
    'active' => 'o:is_active',
    'is active' => 'o:is_active',

    // Automapping from external modules.

    // A file can be a url or a local address (for sideload).
    'file' => 'file',
    'files' => 'file',
    'filename' => 'file',
    'filenames' => 'file',
    'upload' => 'file',
    'sideload' => 'file',
    'file sideload' => 'file',

    // From module Mapping.
    'latitude' => 'o-module-mapping:lat',
    'longitude' => 'o-module-mapping:lng',
    'latitude/longitude' => 'o-module-mapping:lat/o-module-mapping:lng',
    // 'default latitude' => 'mapping_default_latitude',
    // 'default longitude' => 'mapping_default_longitude',
    // 'default zoom' => 'mapping_default_zoom',
    'bounds' => 'o-module-mapping:bounds',

    // From module Folksonomy.
    'tag' => 'o-module-folksonomy:tag',
    'tags' => 'o-module-folksonomy:tag',
    'tagger' => 'o-module-folksonomy:tagging {o:owner}',
    'tag status' => 'o-module-folksonomy:tagging {status}',
    'tag date' => 'o-module-folksonomy:tagging {created}',

    // From module Group.
    'group' => 'o:group',
    'groups' => 'o:group',
];
