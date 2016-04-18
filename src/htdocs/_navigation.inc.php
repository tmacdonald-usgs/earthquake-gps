<?php

$section = '/monitoring/gps';
$url = $_SERVER['REQUEST_URI'];

// Set up page matches for 'Data' tab
$matches = false;
if (
  // index
  preg_match("@^$section/?(index.php)?$@", $url) ||
  // network
  preg_match("@^$section/[\w-]+/?$@", $url) ||
  // station
  preg_match("@^$section/[\w-]+/\w{4}/?$@", $url) ||
  // kinematic, logs, photos, qc
  preg_match("@^$section/[\w-]+/\w{4}/(kinematic|logs|photos|qc)/?$@", $url)) {
    $matches = true;
}

$NAVIGATION =
  navGroup('GPS',
    navItem("$section/", 'Data', $matches) .
    navItem("$section/stations/", 'Station List') .
    navItem("$sectionabout.php", 'About')
  );

print $NAVIGATION;
