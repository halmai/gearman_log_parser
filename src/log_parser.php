<?php
	/** This script creates an SVG time diagram from Gearman log files. 
	 *  The input file must be one (or concatenation of more) log file(s) containing 
	 *  five SQL commands for each calculation. 
	 *  - CREATE TEMP TABLE ...
	 *  - CREATE TEMP TABLE ...
	 *  - SELECT ...
	 *  - DROP TEMP TABLE ...
	 *  - DROP TEMP TABLE ...
	 * 
	 * The lines of the logfile that 
	 *   - start with a timestamp when the query has been finished and written to the log, 
	 *   - followed by some process identifier, 
	 *   - followed by the time length the execution of the query required
	 *   - followed by the query itself,
	 * are parsed. 
	 * Example log line:
	 *  2016-07-13 12:25:17.762125 : (4639_5785a689d6da2)             0.439969, CREATE TEMPORARY TABLE...
	 *
	 * The beginning and the length of each of the query is computed and five
	 * of them are displayed in one row, the next five in a second row, and so on. 
	 * The output of the script has to be directed to an SVG file like this:
	 *
	 * php log_parser.php <gearman.log >result.svg
	 *
	 * The result SVG file contains N rows, five in each of them. where N is the number of the group of fives. For example, if
	 * the log file contained 10 commands:
	 *  - CREATE TEMP TABLE ...
	 *  - CREATE TEMP TABLE ...
	 *  - SELECT ...
	 *  - DROP TEMP TABLE ...
	 *  - DROP TEMP TABLE ...
	 *  - CREATE TEMP TABLE ...
	 *  - CREATE TEMP TABLE ...
	 *  - SELECT ...
	 *  - DROP TEMP TABLE ...
	 *  - DROP TEMP TABLE ...
	 *  then the SVG will contain a graph with 2 lines like this:
	 *  +---------------------------------+
	 *  !111111 222223344 5               !
	 *  !                      66677 89000!
	 *  +---------------------------------+
	 *  The length of the 1....1 block represents the length of the first CREATE TEMP TABLE, 
	 *  the length of 2...2 block represents the second CREATE TEMP TABLE, the 3...3 belongs
	 *  to the first SELECT, and so on. 
	 */

	function get_classification($sql_command) {
		if (strpos($sql_command, "CREATE TEMPORARY TABLE _net_position_calculator_union") === 0) {
			return "CTT-1";  // first CREATE TEMP TABLE
		}
		if (strpos($sql_command, "CREATE TEMPORARY TABLE _net_position_calculator_middle_level") === 0) {
			return "CTT-2";  // second CREATE TEMP TABLE
		}
		if (strpos($sql_command, "SELECT  SUM") === 0) {
			return "SEL";  // main SELECT
		}
		if (strpos($sql_command, "DROP TEMPORARY TABLE _net_position_calculator_middle_level") === 0) {
			return "DTT-1";  // first DROP TEMP TABLE
		}
		if (strpos($sql_command, "DROP TEMPORARY TABLE _net_position_calculator_union") === 0) {
			return "DTT-2";  // second CREATE TEMP TABLE
		}
		var_dump($sql_command);
		die("Cannot understand the SQL command.");
	}

	function get_intervals($input) {
		$lines = explode("\n", $input);
		$ret = array();
		$interval_index = 0;
		foreach ( $lines as $line_index => $line ) {
			$pattern = "/^(20[0-9]{2}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}.[0-9]*) : [0-9()a-z_]* *([0-9.]*), (.*)$/";
			if (preg_match($pattern, $line, $matches)) {
				list($dummy, $end, $length, $sql_command) = $matches;
				$classification = get_classification($sql_command);
				$ret[$interval_index][$classification] = array($end, $length);
				if ($classification === "DTT-2") {
					$interval_index++;
				}
			}
		}
		return $ret;
	}

	function get_timestamp($timestamp) {
		$pattern = "/^([0-9]*)-([0-9]*)-([0-9]*) ([0-9]*):([0-9]*):([0-9]*).([0-9]*)$/";
		preg_match($pattern, $timestamp, $matches);
		$unix_timestamp = mktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1] );
		$shifted_timestamp = $unix_timestamp - mktime(0, 0, 0, 1, 1, 2016); // just to have smaller numbers in order to fit in double with many digits for fraction seconds
		$timestamp = 1 * ($shifted_timestamp . ".". $matches[7]); // add the fraction seconds
		return $timestamp;
	}

	function get_min_max($intervals) {
		$minimal_timestamp = -1;
		$maximal_timestamp = -1;
		foreach ($intervals as $one_set) {
			$end_timestamp = get_timestamp($one_set["CTT-1"][0]);
			$start_timestamp = $end_timestamp - $one_set["CTT-1"][1];
			if ($minimal_timestamp === -1 || $start_timestamp < $minimal_timestamp) {
				$minimal_timestamp = $start_timestamp;
			}

			$end_timestamp = get_timestamp($one_set["DTT-2"][0]);
			if ($maximal_timestamp === -1 || $end_timestamp > $maximal_timestamp) {
				$maximal_timestamp = $end_timestamp;
			}
		}
		return array($minimal_timestamp, $maximal_timestamp);
	}

	function get_height($classification) {
		$vertical_offsets = array(
			"CTT-1" => 0,
			"CTT-2" => 0.1,
			"SEL"   => 0.2,
			"DTT-1" => 0.3,
			"DTT-2" => 0.4,
		);
		$vertical_offset = $vertical_offsets[$classification];
		$ret = 1 - $vertical_offset;
		return $ret;
	}

	function get_style($classification) {
		$styles = array(
			"CTT-1" => "fill:rgb(0,0,255);opacity:0.9",
			"CTT-2" => "fill:rgb(0,255,0);opacity:0.9",
			"SEL"   => "fill:rgb(255,0,0);opacity:0.9",
			"DTT-1" => "fill:rgb(0,255,255);opacity:0.9",
			"DTT-2" => "fill:rgb(255,0,255);opacity:0.9",
		);
		$ret = $styles[$classification];
		return $ret;
	}
	function create_svg($intervals, $scale = 1) {
		list ($minimal_timestamp, $maximal_timestamp) = get_min_max($intervals);
		$size_x = $maximal_timestamp - $minimal_timestamp;
		$size_y = count($intervals);
		$ret = sprintf('<svg width="%f" height="%f">'. "\n", $size_x * $scale, $size_y * $scale);
		$tpl = '    <rect x="%f" y="%f" width="%f" height="%f" style="%s" />'. "\n";

		foreach ($intervals as $index => $one_set) {
			foreach ($one_set as $classification => $one_interval) {
				$end_timestamp = get_timestamp($one_interval[0]);
				$start_timestamp = $end_timestamp - $one_interval[1];
				$start = $start_timestamp - $minimal_timestamp;
				$width = $one_interval[1];
				$height = 1; // get_height($classification);    // use the function if you want to have decreasing height of blocks in a row. 
				$style = get_style($classification);    // use the function if you want to have decreasing height of blocks in a row. 
				$ret .= sprintf($tpl, $start * $scale, $index * $scale, $width * $scale, $height * $scale, $style);
			}
		}

		$ret .= '</svg>'. "\n";
		return $ret;
	}

	$input = file_get_contents("php://stdin");
	$intervals = get_intervals($input);
	$scale = 100;
	$svg = create_svg($intervals, $scale);
	print $svg;


	