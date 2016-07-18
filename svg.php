<?php

class currencyGraph {

	// CONFIG
	private $dir = "./json";
	private $width_of_svg = 840;
	private $height_of_svg = 540;
	private $separator = 1.5;
	private $iterations = 10;

	// GLOBAL VARIABLES
	private $currencies = array();
	private $end_of_graph_x;
	private $end_of_graph_y;
	private $width_of_graph;
	private $height_of_graph;

	// Assign the key values we need to build the graph
	private function assign_colors() {
		$this -> colors = array('USD' => 'green', 'EUR' => 'blue', 'CNY' => 'red');
		return;
	}

	private function assign_start_of_axis() {
		$this -> start_axis = array('USD' => 1, 'EUR' => 1, 'CNY' => 6);
		return;
	}

	private function assign_end_of_axis() {
		$this -> end_axis = array('USD' => 2, 'EUR' => 2, 'CNY' => 11);
		return;
	}

	/*
	 *
	 *
	 * return array $currencies
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

	private function make_currency_array() {
		$files = $this -> grab_json_files();
		$f = 0;
		while ($files[$f]) {
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

	private function set_up_svg_graph() {
		$graph = "<svg id=\"graph\" width=\"" . $this -> width_of_svg . "px\" height=\"" . $this -> height_of_svg . "px\" version=\"1.1\" xmlns=\"http://www.w3.org/2000/svg\">";
		$graph .= "<path stroke=\"black\" stroke-width=\"0.4\" d=\"M10 10 v $this->height_of_graph\"/>";
		$graph .= "<path stroke=\"black\" stroke-width=\"0.4\" d=\"M$this->end_of_graph_x 10 v $this->height_of_graph\"/>";
		$graph .= "<path stroke=\"black\" stroke-width=\"0.4\" d=\"M10 $this->end_of_graph_y h $this->width_of_graph\"/>";
		return $graph;
	}

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

	function add_weeks_months_years() {
		$d = 0;
		if ($this -> currencies[$d]['file']) {
			$weeklegendx = ($this -> width_of_graph/2) -20;
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

	public function assemble_svg() {
		$graph = $this -> set_up_svg_graph();
		$graph .= $this -> set_up_svg_axis();
		$graph .= $this -> draw_main_graphlines('USD');
		$graph .= $this -> draw_main_graphlines('EUR');
		$graph .= $this -> draw_main_graphlines('CNY');
		$graph .= $this -> add_weeks_months_years();
		$graph .= $this -> add_key();
		$logox = $this -> end_of_graph_x -110;
		$logoy = $this -> end_of_graph_y -15;
		$graph .= "<text x=\"$logox\" y=\"$logoy\" font-family=\"sans-serif\" font-size=\"16px\" fill=\"black\">shortdark.net</text>";
		$graph .= "</svg>";

		return $graph;
	}

	function __construct() {
		if (intval($_GET['wide']) and 840>intval($_GET['wide'])) {
			$this -> width_of_svg = intval($_GET['wide']);
		}
		if (intval($_GET['tall']) and 650>intval($_GET['tall'])) {
			$this -> height_of_svg = intval($_GET['tall']);
		}
		$this -> end_of_graph_x = $this -> width_of_svg - 30;
		$this -> end_of_graph_y = $this -> height_of_svg - 30;
		$this -> width_of_graph = $this -> width_of_svg - 40;
		$this -> height_of_graph = $this -> height_of_svg - 40;
		$this -> assign_colors();
		$this -> assign_start_of_axis();
		$this -> assign_end_of_axis();
		$this -> make_currency_array();
	}

}

$draw_graph = new currencyGraph();
echo $draw_graph -> assemble_svg();
?>