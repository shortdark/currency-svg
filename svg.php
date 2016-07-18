<?php
// If the page isn't referred by http://www.shortdark.net/currency/ then it's either from elsewhere, or the svg.php
// is being opened on it's own. We don't want this.
if("http://www.shortdark.net/currency/"!=substr(filter_var($_SERVER["HTTP_REFERER"], FILTER_SANITIZE_URL),0,34)){
	die('Please go to <a href="http://www.shortdark.net/currency/">http://www.shortdark.net/currency/</a>.');
}

/**
 * CURRENCYGraph - collects data relating to currency exchange rates from JSON files and plots it as a SVG graph.
 * PHP Version 5.4
 * Version 0.1.2
 * @package CURRENCYGraph
 * @link https://github.com/shortdark/currency-svg/
 * @author Neil Ludlow (shortdark) <neil@shortdark.net>
 * @copyright 2016 Neil Ludlow
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 * @note This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 */

class CURRENCYGraph {

	/**
	 * ################
	 * ##
	 * ##  CONFIG
	 * ##
	 * ################
	 */

	/*
	 * Where the JSON files are located relative to the script svg.php
	 */
	private $dir = "./json";
	/*
	 * The total width of the SVG, can be overridden by the size of the screen
	 */
	private $width_of_svg = 840;
	/*
	 * The total height of the SVG, can be overridden by the size of the screen
	 */
	private $height_of_svg = 540;
	/*
	 * The number of pixels per data point on the x-axis
	 */
	private $separator = 1.5;
	/*
	 * The number of horizontal lines along the y-axis
	 */
	private $iterations = 10;

	/**
	 * ################
	 * ##
	 * ##  GLOBAL VARIABLES
	 * ##
	 * ################
	 */
	/*
	 * The array for the currency data
	 */
	private $currencies = array();
	/*
	 * From the left-side of the SVG to the right of the graph
	 */
	private $end_of_graph_x;
	/*
	 * From the top of the SVG to the bottom of the graph
	 */
	private $end_of_graph_y;
	/*
	 * The width of the graph, i.e. between the two axes
	 */
	private $width_of_graph;
	/*
	 * The height of the graph
	 */
	private $height_of_graph;
	/*
	 * The number of records we need to fill the size of graph we're making.
	 * This is so that we don't have to store much more data than we need, saving resources and time.
	 */
	private $days_for_graph;

	/**
	 * ################
	 * ##
	 * ##  SETUP METHODS
	 * ##
	 * ################
	 */

	/*
	 * Set the color of the 3 graphs
	 *
	 * return
	 */
	private function assign_colors() {
		$this -> colors = array('USD' => 'green', 'EUR' => 'blue', 'CNY' => 'red');
		return;
	}

	/*
	 * Set the lowest value for each graph
	 *
	 * return
	 */
	private function assign_start_of_axis() {
		$this -> start_axis = array('USD' => 1, 'EUR' => 1, 'CNY' => 6);
		return;
	}

	/*
	 * Set the highest value for each graph
	 *
	 * return
	 */
	private function assign_end_of_axis() {
		$this -> end_axis = array('USD' => 2, 'EUR' => 2, 'CNY' => 11);
		return;
	}

	/*
	 * Get the number of days we need to fill the graph
	 *
	 * return
	 */
	private function assign_number_of_days() {
		// Only add the number of days for the size of graph that is being called
		$this -> days_for_graph = intval($this -> width_of_graph / $this -> separator);
		return;
	}

	/**
	 * ################
	 * ##
	 * ##  METHODS
	 * ##
	 * ################
	 */

	/*
	 * Get the names of the JSON files from directory, specified in $this->dir
	 * and return the array in reverse order (with the highest date first)
	 *
	 * return array $files
	 */
	private function grab_json_files() {
		$dh = opendir($this -> dir);
		while (false !== ($filename = readdir($dh))) {
			if ("." != $filename and ".." != $filename and ".json" == substr($filename, -5)) {
				$files[] = $filename;
			}
		}
		closedir($dh);
		rsort($files);
		return $files;
	}

	/*
	 * Read the required volume of files for the size of graph and make an array
	 * $this->currencies
	 *
	 * return
	 */
	private function make_currency_array() {
		$files = $this -> grab_json_files();
		$f = 0;
		while ($files[$f] and $this -> days_for_graph >= $f) {
			$filename = $files[$f];
			$currency = file_get_contents("$this->dir/$filename");
			$unravelled = json_decode($currency, TRUE);
			if ($unravelled['rates']['USD'] and $unravelled['rates']['EUR'] and $unravelled['rates']['CNY']) {
				// To get around the fact that sometimes a month or year starts on a weekend we include the data from
				// weekends too, even though it'll be the same data as the friday. This is the reason for using the
				// filename as the date, not the actual date value from the JSON (which will be the friday x3 at a weekend).
				$this -> currencies[$f]['file'] = substr($filename, 0, 10);
				$this -> currencies[$f]['USD'] = $unravelled['rates']['USD'];
				$this -> currencies[$f]['EUR'] = $unravelled['rates']['EUR'];
				$this -> currencies[$f]['CNY'] = $unravelled['rates']['CNY'];
			}
			$f++;
		}
		return;
	}

	/*
	 * Draw the opening tag of the SVG and the basic axis lines
	 *
	 * return $graph
	 */
	private function set_up_svg_graph() {
		$graph = "<svg id=\"graph\" width=\"" . $this -> width_of_svg . "px\" height=\"" . $this -> height_of_svg . "px\" version=\"1.1\" xmlns=\"http://www.w3.org/2000/svg\">";
		$graph .= "<path stroke=\"black\" stroke-width=\"0.4\" d=\"M10 10 v $this->height_of_graph\"/>";
		$graph .= "<path stroke=\"black\" stroke-width=\"0.4\" d=\"M$this->end_of_graph_x 10 v $this->height_of_graph\"/>";
		$graph .= "<path stroke=\"black\" stroke-width=\"0.4\" d=\"M10 $this->end_of_graph_y h $this->width_of_graph\"/>";
		return $graph;
	}

	/*
	 * Draws the axis labels with horizontal lines. The USD and EUR graphs use one axis,
	 * CNY uses another (red).
	 *
	 * return $graph
	 */
	private function set_up_svg_axis() {
		/*
		 Draw main axis for USD/EUR
		 */
		$start_of_axis = $this -> start_axis['USD'];
		$end_of_axis = $this -> end_axis['USD'];
		$data_range = $end_of_axis - $start_of_axis;
		$value_per_iteration = $data_range / $this -> iterations;

		for ($i = 0; $i <= $this -> iterations; $i++) {
			$heightatt = $this -> end_of_graph_y - ($i * $this -> height_of_graph / $this -> iterations);
			$textval = $start_of_axis + ($i * $value_per_iteration);
			$graph .= "<text x=\"1\" y=\"$heightatt\" font-family=\"sans-serif\" font-size=\"12px\" fill=\"black\">$textval</text>";
			$graph .= "<path stroke=\"black\" stroke-width=\"0.2\" d=\"M10 $heightatt h $this->width_of_graph\"/>";
		}

		/*
		 Draw a second axis for CNY in red
		 */
		$start_of_axis = $this -> start_axis['CNY'];
		$end_of_axis = $this -> end_axis['CNY'];
		$data_range = $end_of_axis - $start_of_axis;
		$value_per_iteration = $data_range / $this -> iterations;
		$second_axis = $this -> end_of_graph_x + 1;
		for ($i = 0; $i <= $this -> iterations; $i++) {
			$heightatt = $this -> end_of_graph_y - ($i * $this -> height_of_graph / $this -> iterations);
			$textval = $start_of_axis + ($i * $value_per_iteration);

			$graph .= "<text x=\"$second_axis\" y=\"$heightatt\" font-family=\"sans-serif\" font-size=\"12px\" fill=\"{$this->colors['CNY']}\">$textval</text>";
		}

		return $graph;
	}

	/*
	 * Draws the 3 graph lines in the colors specified in the set up methods.
	 *
	 * return $line
	 */
	private function draw_main_graphlines($curr) {
		$g = 0;
		$color = $this -> colors[$curr];

		$start_of_axis = $this -> start_axis[$curr];
		$end_of_axis = $this -> end_axis[$curr];
		$pixels_per_unit = $this -> height_of_graph / ($end_of_axis - $start_of_axis);
		if ($this -> currencies[$g][$curr]) {
			while ($this -> currencies[$g][$curr]) {
				$xvalue = $this -> end_of_graph_x - ($g * $this -> separator);
				$currencyval = floatval($this -> currencies[$g][$curr]);
				$yvalue = $this -> end_of_graph_y - (($currencyval - $start_of_axis) * $pixels_per_unit);
				if (10 <= $xvalue) {
					if (0 == $g) {
						$line = "<path d=\"M$xvalue $yvalue";
					} else {
						$line .= " L$xvalue $yvalue";
					}
				}
				$g++;
			}
			$line .= "\" stroke-linejoin=\"round\" stroke=\"$color\" fill=\"none\"/>";
		}

		return $line;
	}

	/*
	 * Add vertical lines to mark the weeks, months and years. As Friday, Saturday and Sunday have the same value
	 * I've made the new week start on Saturday. This method also labels every month, year and every 5th week.
	 *
	 * return $graph
	 */
	function add_weeks_months_years() {
		$d = 0;
		if ($this -> currencies[$d]['file']) {
			$weeklegendx = ($this -> width_of_graph / 2) - 20;
			$graph .= "<text x=\"$weeklegendx\" y=\"30\" font-family=\"sans-serif\" font-size=\"12px\" fill=\"black\">Week Numbers</text>";
			while ($this -> currencies[$d]['file']) {
				$dateval = $this -> currencies[$d]['file'];
				$xvalue = $this -> end_of_graph_x - ($d * $this -> separator);
				if (10 <= $xvalue) {
					$year = substr($dateval, 0, 4);
					$month = substr($dateval, 5, 2);
					$day = intval(substr($dateval, 8, 2));
					$numericday = date("w", mktime(0, 0, 0, $month, $day, $year));
					if (6 == $numericday) {
						$weeknumber = date("W", mktime(0, 0, 0, $month, $day, $year)) + 1;
						$graph .= "<path stroke=\"green\" stroke-width=\"0.2\" d=\"M$xvalue 10 v $this->height_of_graph\"/>";
						if (0 == $weeknumber % 5) {
							$graph .= "<text x=\"$xvalue\" y=\"50\" font-family=\"sans-serif\" font-size=\"12px\" fill=\"black\">$weeknumber</text>";
						}
					}
					$dayofmonth = date("j", mktime(0, 0, 0, $month, $day, $year));
					if (1 == $dayofmonth) {
						$dayofyear = date("z", mktime(0, 0, 0, $month, $day, $year));
						$daywords = date("j", mktime(0, 0, 0, $month, $day, $year));
						$monthwords = date("M", mktime(0, 0, 0, $month, $day, $year));
						$yearwords = date("Y", mktime(0, 0, 0, $month, $day, $year));
						$graph .= "<path stroke=\"black\" stroke-width=\"0.4\" d=\"M$xvalue 10 v $this->height_of_graph\"/>";
						$monthlegendy = $this -> height_of_graph + 20;
						$graph .= "<text x=\"$xvalue\" y=\"$monthlegendy\" font-family=\"sans-serif\" font-size=\"12px\" fill=\"black\">$monthwords</text>";
						if (0 == $dayofyear) {
							$yearlegendy = $this -> height_of_graph + 35;
							$graph .= "<text x=\"$xvalue\" y=\"$yearlegendy\" font-family=\"sans-serif\" font-size=\"12px\" fill=\"black\">$yearwords</text>";
						}

					}
					if ("2016-06-23" == $dateval) {
						// date of Brexit vote
						$graph .= "<path stroke=\"yellow\" stroke-width=\"1\" d=\"M$xvalue 10 v $this->height_of_graph\"/>";
					}
				}
				$d++;
			}
		}
		return $graph;
	}

	/*
	 * Draw a white semi-transparent box and draw the key for the graph on top.
	 *
	 * return $graph
	 */
	private function add_key() {
		$graph .= "<path fill-opacity=\"0.9\" d=\"M20 12 v140 h125 v-140 h-125\" fill=\"white\"></path>";
		$graph .= "<text x=\"50\" y=\"40\" font-family=\"sans-serif\" font-size=\"16px\" fill=\"black\" text-decoration=\"underline\">KEY</text>";
		$graph .= "<path d=\"M30 55 L40 75\" stroke-linejoin=\"round\" stroke=\"{$this->colors['USD']}\" fill=\"none\"/>";
		$graph .= "<text x=\"50\" y=\"70\" font-family=\"sans-serif\" font-size=\"16px\" fill=\"black\">USD {$this->currencies[0]['USD']}</text>";
		$graph .= "<path d=\"M30 85 L40 105\" stroke-linejoin=\"round\" stroke=\"{$this->colors['EUR']}\" fill=\"none\"/>";
		$graph .= "<text x=\"50\" y=\"100\" font-family=\"sans-serif\" font-size=\"16px\" fill=\"black\">EUR {$this->currencies[0]['EUR']}</text>";
		$graph .= "<path d=\"M30 115 L40 135\" stroke-linejoin=\"round\" stroke=\"{$this->colors['CNY']}\" fill=\"none\"/>";
		$graph .= "<text x=\"50\" y=\"130\" font-family=\"sans-serif\" font-size=\"16px\" fill=\"black\">CNY {$this->currencies[0]['CNY']}</text>";
		return $graph;
	}

	/*
	 * Assemble the graph and output it.
	 *
	 * return $graph
	 */
	public function assemble_svg() {
		$graph = $this -> set_up_svg_graph();
		$graph .= $this -> set_up_svg_axis();
		$graph .= $this -> draw_main_graphlines('USD');
		$graph .= $this -> draw_main_graphlines('EUR');
		$graph .= $this -> draw_main_graphlines('CNY');
		$graph .= $this -> add_weeks_months_years();
		$graph .= $this -> add_key();
		$logox = $this -> end_of_graph_x - 110;
		$logoy = $this -> end_of_graph_y - 15;
		$graph .= "<text x=\"$logox\" y=\"$logoy\" font-family=\"sans-serif\" font-size=\"16px\" fill=\"black\">shortdark.net</text>";
		$graph .= "</svg>";

		return $graph;
	}

	function __construct() {
		if (intval($_GET['wide']) and 840 > intval($_GET['wide'])) {
			$this -> width_of_svg = intval($_GET['wide']);
		}
		if (intval($_GET['tall']) and 650 > intval($_GET['tall'])) {
			$this -> height_of_svg = intval($_GET['tall']);
		}
		$this -> end_of_graph_x = $this -> width_of_svg - 30;
		$this -> end_of_graph_y = $this -> height_of_svg - 30;
		$this -> width_of_graph = $this -> width_of_svg - 40;
		$this -> height_of_graph = $this -> height_of_svg - 40;
		$this -> assign_colors();
		$this -> assign_start_of_axis();
		$this -> assign_end_of_axis();
		$this -> assign_number_of_days();
		$this -> make_currency_array();
	}

}

$draw_graph = new CURRENCYGraph();
echo $draw_graph -> assemble_svg();
?>