# Gearman Log Parser

This script creates an SVG time diagram from Gearman log files. 
The input file must be one (or concatenation of more) log file(s) containing five SQL commands for each gearman calculation. 
 - CREATE TEMP TABLE ...
 - CREATE TEMP TABLE ...
 - SELECT ...
 - DROP TEMP TABLE ...
 - DROP TEMP TABLE ...

The lines of the logfile that 
  - start with a timestamp when the query has been finished and written to the log, 
  - followed by some process identifier, 
  - followed by the time length the execution of the query required
  - followed by the query itself,

are parsed. Lines not matching this pattern are ignored. 

Example log line:

```
2016-07-13 12:25:17.762125 : (4639_5785a689d6da2)             0.439969, CREATE TEMPORARY TABLE...
```

The beginning and the length of each of the queries are computed and five of them are displayed in one row, the next five in a second row, and so on. 

The output of the script has to be directed to an SVG file like this:
```
php log_parser.php <gearman.log >result.svg
```

The result SVG file contains N rows, five in each of them. where N is the number of the group of fives. For example, if
the log file contained 10 commands:
 - CREATE TEMP TABLE ...
 - CREATE TEMP TABLE ...
 - SELECT ...
 - DROP TEMP TABLE ...
 - DROP TEMP TABLE ...
 - CREATE TEMP TABLE ...
 - CREATE TEMP TABLE ...
 - SELECT ...
 - DROP TEMP TABLE ...
 - DROP TEMP TABLE ...
 
then the SVG will contain a graph with 2 lines like this:
```
+---------------------------------+
!111111 222223344 5               !
!                      66677 89000!
+---------------------------------+
```
The length of the `1....1` block represents the length of the first `CREATE TEMP TABLE`, 
the length of `2...2` block represents the second `CREATE TEMP TABLE`, the `3...3` belongs
to the first `SELECT...`, and so on. 

For sample input and output, check the `data/` directory. 

## How to view the result

Unfortunately there are many bad SVG viewers. Some of them distort the result by adding blur effects and other annoying stuffs. To avoid this things, I used [Inkscape](http://www.inkscape.org) which displays SVG files very precisely. 