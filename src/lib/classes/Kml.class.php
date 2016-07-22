<?php

include_once '../conf/config.inc.php'; // app config
include_once '../lib/classes/Db.class.php'; // db connector, queries

include_once '../lib/classes/Logsheet.class.php'; // logsheet model
include_once '../lib/classes/LogsheetCollection.class.php'; // logsheet collection

/**
 * Generate KML files for GPS stations
 *
 * @author Scott Haefner <shaefner@usgs.gov>
 */
class Kml {
  private $_domain;
  private $_lats;
  private $_lons;
  private $_meta;
  private $_network;
  private $_sortField;
  private $_stations;

  public function __construct($network=NULL) {
    $this->_domain = $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'];
    $this->_meta = [
      'last_obs' => [
        'name' => $network . ' Network (sorted by last year occupied)',
        'folder' => 'Last surveyed in %s',
      ],
      'station' => [
        'name' => $network . ' Network (sorted by station name)',
      ],
      'total_years' => [
        'name' => $network . ' Network (sorted by total years occupied)',
        'folder' => '%s year(s) between first/last surveys',
      ]
    ];
    $this->_network = $network;
    $this->_sortField = 'station'; // sorted by station name by default
    $this->_stations = $this->_getStations();
  }

  /**
   * Get KML Body
   *
   * @return $body {String}
   */
  private function _getBody () {
    $body = '';
    $containsFolders = true;
    $prevValue = '';
    $sortField = $this->_sortField;
    $this->_lats = [];
    $this->_lons = [];

    // Don't create folders when sorting by station name
    if ($sortField === 'station') {
      $containsFolders = false;
    }

    foreach($this->_stations as $station) {

      // Skip continous stations unless we are creating kml sorted by station
      if ($station->stationtype === 'campaign' || $sortField === 'station') {
        // Create folders for grouping stations
        if ($containsFolders) {
          $value = $station->$sortField;
          if ($value !== $prevValue) {
            // Close previous folder
            if ($prevValue) {
              $body .= "\n    </Folder>";
            }
            // Open new folder
            $sub = $value;
            if (!$value) {
              $sub = '[unknown]';
            }
            $folder = sprintf($this->_meta[$sortField]['folder'], $sub);
            $body .= "\n    <Folder><name>$folder</name><open>0</open>";

            $prevValue = $value;
          }
        }

        // Store station lat, lon in array for calculating bounds of all stations
        array_push($this->_lats, $station->lat);
        array_push($this->_lons, $station->lon);

        $placeMark = $this->_getPlaceMark($station);
        $body .= "\n$placeMark";
      }
    }

    // Close final folder (not using folders when sorting by station)
    if ($sortField !== 'station') {
      $body .= "\n    </Folder>";
    }

    return $body;
  }

  /**
   * Get KML footer
   *
   * @return {String}
   */
  private function _getFooter () {
    return "\n  </Document>\n</kml>";
  }

  /**
   * Get KML header
   *
   * @return $header {String}
   */
  private function _getHeader () {
    $name = $this->_meta[$this->_sortField]['name'];
    $timestamp = date('D M j, Y H:i:s e');
    $latCenter = (max($this->_lats) + min($this->_lats)) / 2;
    $lonCenter = (max($this->_lons) + min($this->_lons)) / 2;
    $legendUrl = sprintf ('http://%s%s/img/kmlLegend-%s.png',
      $this->_domain,
      $GLOBALS['MOUNT_PATH'],
      $this->_sortField
    );

    $header = '<?xml version="1.0" encoding="UTF-8"?>
    <kml xmlns="http://earth.google.com/kml/2.1">
      <Document>
        <name>' . $name . '</name>
        <description>' . $timestamp . '</description>
        <LookAt>
          <heading>0</heading>
          <latitude>' . $latCenter . '</latitude>
          <longitude>' . $lonCenter . '</longitude>
          <range>1000000</range>
          <tilt>0</tilt>
        </LookAt>
        <ScreenOverlay>
          <name>Legend</name>
          <open>0</open>
          <overlayXY x="0" y="1" xunits="fraction" yunits="fraction"/>
          <screenXY x=".005" y=".995" xunits="fraction" yunits="fraction"/>
          <size x="150" y="0" xunits="pixels" yunits="pixels"/>
          <visibility>1</visibility>
          <Icon>
            <href>' . $legendUrl . '</href>
          </Icon>
        </ScreenOverlay>
        <Style id="marker">
          <BalloonStyle><text><![CDATA[
            <style>
              ul { margin: 0; padding-left: 0em; line-height: 1.4; }
              .coords { margin-top: -1em; }
              .data { border-top: 1px solid #eee; padding-top: 1em; }
            </style>
            $[description]
          ]]></text></BalloonStyle>
        </Style>';

    return $header;
  }

  /**
   * Get appropriate icon based on station properties for current sort type
   *
   * @param $station {Object}
   *
   * @return $icon {String}
   *     Absolute URL of icon
   */
  private function _getIcon ($station) {
    $shapes = [
      'campaign' => 'triangle',
      'continuous' => 'square'
    ];

    if ($this->_sortField === 'station') {
      $color = 'grey';
    }
    else {
      if ($this->_sortField === 'last_obs') {
        if ($station->last_obs) {
          $years = ceil(date('Y') - $station->last_obs);
        } else { // set $years to '0' if $station->last_obs is empty
          $years = 0;
        }
      } else if ($this->_sortField === 'total_years') {
        $years = $station->total_years;
      }

      // Get color
      if ($years > 15) {
        $color = 'red';
      } else if ($years > 12) {
        $color = 'orange';
      } else if ($years > 9) {
        $color = 'yellow';
      } else if ($years > 6) {
        $color = 'green';
      } else if ($years > 3) {
        $color = 'blue';
      } else if ($years > 0) {
        $color = 'purple';
      } else {
        $color = 'grey';
      }
    }

    $icon = sprintf ('http://%s%s/img/pin-s-%s+%s.png',
      $this->_domain,
      $GLOBALS['MOUNT_PATH'],
      $shapes[$station->stationtype],
      $color
    );

    return $icon;
  }

  /**
   * Get a list of logsheets for a station
   *
   * @param $station {String}
   *
   * @return $logsheetCollection {Object}
   */
  private function _getLogSheets ($station) {
    $dir = sprintf('%s/stations/%s.dir/%s/logsheets',
      $GLOBALS['DATA_DIR'],
      substr($station, 0, 1),
      $station
    );

    // sort ASC so that 'Front' page (1) is listed before 'Back' page (2)
    $files = getDirContents($dir, $order=SCANDIR_SORT_ASCENDING);

    // Add logsheets to collection
    $logsheetCollection = new LogsheetCollection($station, $this->_network);
    foreach ($files as $file) {
      $logsheetModel = new Logsheet($file);
      $logsheetCollection->add($logsheetModel);
    }

    // Sort collection by date DESC (default)
    $logsheetCollection->sort();

    return $logsheetCollection;
  }

  /**
   * Get KML placemark for a station
   *
   * @param $station {Object}
   *
   * @return $placeMark {String}
   */
  private function _getPlaceMark ($station) {
    $data_collected = false;
    $display_lat = number_format($station->lat, 5, '.', '');
    $display_lon = number_format($station->lon, 5, '.', '');
    $display_station = strtoupper($station->station);
    $icon = $this->_getIcon($station);
    $logsheets_html = '';

    // Get logsheets for each station (but only if filtered by network)
    if ($this->_network) {
      $logsheetsCollection = $this->_getLogSheets($station->station);

      // Create HTML for logsheets
      $logsheets_html = '<ul>';
      foreach($logsheetsCollection->logsheets as $date => $logsheets) {
        $data_collected = true;
        $logsheets_html .= sprintf('<li><a href="http://%s%s/%s">%s</a></li>',
          $this->_domain,
          $logsheetsCollection->path,
          $logsheets[0]->file, // front page or txt-based log
          date('M d, Y', strtotime($date))
        );
      }
      $logsheets_html .= '</ul>';

      if ($data_collected) {
        $logsheets_html = '<p class="data">GPS data was collected on the
          following date(s):</p>' . $logsheets_html;
      }
    }

    // Create HTML for description
    $description_html = sprintf('<h1>%s</h1>
      <p class="coords">%s, %s</p>
      <p><a href="http://%s%s/%s/%s">Station Details</a></p>
      %s',
      $display_station,
      $display_lat,
      $display_lon,
      $this->_domain,
      $GLOBALS['MOUNT_PATH'],
      $station->network,
      $station->station,
      $logsheets_html
    );

    $placeMark = '    <Placemark>
      <name>' . $display_station . '</name>
      <description>' . $description_html . '</description>
      <styleUrl>#marker</styleUrl>
      <visibility>1</visibility>
      <LookAt>
        <longitude>' . $station->lon . '</longitude>
        <latitude>' . $station->lat . '</latitude>
        <range>10000</range>
      </LookAt>
      <Point>
        <coordinates>' . $station->lon . ',' . $station->lat . ',0</coordinates>
      </Point>
      <Snippet>' . $display_lat . ', ' . $display_lon . '</Snippet>
      <Style>
        <IconStyle><Icon><href>'. $icon . '</href></Icon></IconStyle>
      </Style>
    </Placemark>';

    return $placeMark;
  }

  /**
   * Get DB recordset containing all stations in network
   *
   * @return {Object}
   */
  private function _getStations() {
    $db = new Db;

    // Db query result: all stations in a given network
    $result = $db->queryStations($this->_network);

    return $result->fetchAll(PDO::FETCH_OBJ);
  }

  /**
   * Render KML content
   */
  public function render () {
    $body = $this->_getBody();
    print $this->_getHeader(); // header depends on arrays set by _getBody()
    print $body;
    print $this->_getFooter();
  }

  /**
   * Set PHP Headers for triggering file download w/ no caching
   */
  public function setPhpHeaders () {
    $basename = 'GPS';
    if ($this->_network) {
      $basename = $this->_network;
    }
    $expires = date(DATE_RFC2822);
    $filename = $basename . '-' . $this->_sortField . '.kml';

    header('Cache-control: no-cache, must-revalidate');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Type: application/xml');
    header("Expires: $expires");
  }

  /**
   * Sort stations by last observation or total years
   * (initial Db result is sorted by station name)
   *
   * @param $col {String <last_obs | total_years>}
   */
  public function sort ($col) {
    if ($col === 'last_obs') {
      usort($this->_stations, function ($a, $b) {
        return intval($b->last_obs) - intval($a->last_obs);
      });
      $this->_sortField = $col;
    }
    else if ($col === 'total_years') {
      usort($this->_stations, function ($a, $b) {
        return intval($b->total_years) - intval($a->total_years);
      });
      $this->_sortField = $col;
    }
  }
}
