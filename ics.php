<?php
	$curl_handle = curl_init();
	curl_setopt($curl_handle, CURLOPT_URL,'http://www.gmnscoutstraining.co.uk/events.php');
	curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT,2);
	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER,1);
	$data = curl_exec($curl_handle);
	curl_close($curl_handle);

	$events = array();
	if(!empty($data)) {
		$parts = explode("</table></td><td width=\"80%\" valign=\"top\">", $data);
		if(count($parts) > 1) {
			$parts = explode("</table>", $parts[1]);
			if(count($parts) > 0) {
				$data = $parts[0];
				unset($parts);

				$rows = explode("<tr", $data);
				unset($data);
				if(count($rows) > 2) {
					$rows = array_slice($rows, 3);

					foreach($rows as &$row) {
						$parts = explode("</tr>", $row);
						unset($parts[count($parts)-1]);
						$row = join("</tr>", $parts);
						$columns = explode("<td", $row);

						if(count($columns) != 6) {
							break;
						}

						foreach($columns as &$column) {
							$column = preg_replace("/.*<font size=\"2\">(.*)<\/font>.*/", "$1", $column);
						}

						// Parse out map url
						$map = '';
						if (preg_match("/<a.*href=\"(.*)\" target=.*>(.*)<\/a>/", $columns[2], $matches)) {
							$columns[2] = $matches[2];
							$map = $matches[1];
						}

						// Make a proper date
						$date = trim(html_entity_decode($columns[3]));
						$time = trim(html_entity_decode($columns[4]));

						$dparts = explode(" ", $date);
						unset($dparts[0]);
						$dparts[1] = str_replace(array("st", "nd", "rd", "th"), "", $dparts[1]);
						$date = join(" ", $dparts);
						unset($dparts);

						$dtime = $date . ' ' . $time;
						$start_time = strptime($dtime, "%d %B %Y %H:%M");
						$start_time['tm_year'] = $start_time['tm_year'] + 1900;
						$start_time['tm_mon'] = $start_time['tm_mon'] + 1;

						$start_time = mktime(
							$start_time['tm_hour'],
							$start_time['tm_min'],
							$start_time['tm_sec'],
							$start_time['tm_mon'],
							$start_time['tm_mday'],
							$start_time['tm_year']);
						$start_time = date("Ymd\THis", $start_time);

						// We don't actually have an end time so lets fake it
						$end_time = $start_time;

						$event = str_replace(",", "\,", trim(html_entity_decode(str_replace('&nbsp;', '', $columns[1]))));
						$venue = str_replace(",", "\,", trim(html_entity_decode(str_replace('&nbsp;', '', $columns[2]))));
						$map = str_replace(",", "\,", trim(html_entity_decode(str_replace('&nbsp;', '', $map))));
						$modules = str_replace(",", "\,", trim(html_entity_decode(str_replace('&nbsp;', '', str_replace("</tr>", "", $columns[5])))));

						if(!empty($event) || !empty($venue) || !empty($modules)) {
							$events[] = array(
								"event" => $event,
								"venue" => $venue,
								"map" => $map,
								"modules" => $modules,
								"start_time" => $start_time,
								"end_time" => $end_time,
							);
						}
					}
				}
			}
		}
	}

	header('Content-Type: text/calendar');
	header('Content-Disposition: attachment; filename="GMN scout training.ics"');
	header('Vary: Cookie,Accept-Encoding');
	
	print "BEGIN:VCALENDAR\r\n";
	print "VERSION:2.0\r\n";
	print "PRODID:-//GMN training events//NONSGML v1.0//EN\r\n";
	print "X-WR-CALNAME:GMN training events\r\n";
	print "X-WR-CALDESC:GMN training events\r\n";
	print "X-WR-TIMEZONE: GMT\r\n";
	print "CALSCALE:GREGORIAN\r\n";
	print "METHOD:PUBLISH\r\n";

	foreach($events as $event) {
		print "BEGIN:VEVENT\r\n";

		// ID
		print "UID:" . md5($event['event'] . $event['modules']) . "@http://gmnscoutstraining.co.uk\r\n";

		// Times
		print "DTSTART:" . $event['start_time'] . "Z\r\n";
		print "DTEND:" . $event['end_time'] . "Z\r\n";

		// Basic stuff
		print "LOCATION:" . $event['venue'] . "\r\n";
		print "SUMMARY:" . $event['event'] . "\r\n";

		// Description stuff
		print "DESCRIPTION:\"" . $event['event'] . "\" (" . $event['modules'] . ")\\n";
		print "Modules: " . $event['modules'] . "\\n";

		// Location stuff
		print "Location: " . $event['venue'] . "\\n";
	       	print "Map: " . $event['map'] . "\r\n";

		print "END:VEVENT\r\n";
	}

	print "END:VCALENDAR\r\n";
?>
