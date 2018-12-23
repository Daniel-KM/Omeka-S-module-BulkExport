# Import plugin for Omeka S

Yet another import plugin for Omeka. This one intends to be easily extensible
by other plugins.

The two main concepts are readers and processors. Readers read data from
a source (file, url, ...) and make it accessible for processors which turn
these data into Omeka objects (items, collections, files, ...).

This plugin defines a reader for CSV files and a processor that create
items based on a user-defined mapping.

Note : if your only need is to import a CSV file into Omeka items, you should
probably use [CSV Import plugin], which does a perfect job for that.

[CSV Import plugin]: https://omeka.org/s/modules/CSVImport/
