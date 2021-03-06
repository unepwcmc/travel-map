<?php

$javaPath = '../../cost/artifacts/Juliet_jar/Juliet.jar';
$databasePath = '../database.db';

try {
    $dbconn = new SQLite3('../database.db');
    $dbconn->exec("PRAGMA foreign_keys=ON");
} catch (Exception $e) {
    //TODO: fix this
    error("Couldn't connect to DB.");
}


/*
<?php
$db = new SQLite3;
$statement = $db->prepare('SELECT * FROM table WHERE id = :id;');
$statement->bindValue(':id', $id);
$result = $statement->execute();
?>
 */

function computeMatches($table_name, $id)
{
    global $dbconn, $javaPath, $databasePath;
    //do stuff;
    $doRun = true;


    if ($table_name === 'wishes') {
        //Wish check
        $rows = $dbconn->query("SELECT * FROM suggestions WHERE wish_id = " . strval($id));
        while ($row = $rows->fetchArray()) {
            $doRun = false;
            break;
        }
    } else {
        //Dependency check
        $rows = $dbconn->query("SELECT * FROM suggestions WHERE " . $table_name . "__dep_id" . " = " . strval($id));
        while ($row = $rows->fetchArray()) {
            $doRun = false;
            break;
        }
    }

    if ($doRun) {

        $command = "java -jar " . getcwd() . "/" . $javaPath . " " . getcwd() . "/" . $databasePath . " 0 " . $table_name . " " . strval($id);
        error_log(var_export($command,true));
        exec($command);

        //old version
       // exec("java -jar " . getcwd() . "/" . $javaPath . " " . getcwd() . "/" . $databasePath . " " . $table_name . " " . strval($id));
    }

    return $doRun;
}

function updateSuggestion($accepted_suggestion_id){
    global $javaPath, $databasePath;
    $command = "java -jar " . getcwd() . "/" . $javaPath . " " . getcwd() . "/" . $databasePath . " 1 " . strval($accepted_suggestion_id);
    exec($command);
}

function answerJsonAndDie($obj)
{
    header('Content-Type: application/json');
    echo json_encode($obj, JSON_NUMERIC_CHECK);
    die();
}

function error($error)
{
    $obj = new stdClass();
    $obj->error = $error;
    answerJsonAndDie($obj);
}

function removeNumericKeys($array)
{
    foreach ($array as $key => $value) {
        if (is_int($key)) {
            unset($array[$key]);
        }
    }
    return $array;
}

function getAllTables($params)
{
    global $dbconn;
    $rows = $dbconn->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    $result = array();
    while ($row = $rows->fetchArray()) {
        array_push($result, (object)$row);
    }
    return ($result);
}

function getAllOrganisationNames($params)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT name FROM organisations");
    $rows = $stmt->execute();
    $result = array();
    while ($row = $rows->fetchArray()) {
        $result[] = $row['name'];
    }
    return $result;
}

function getWishesWithinTimeframe($params)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT wishes.id, wishes.name, wc.startTime, wc.endTime, wishes.wisher_id FROM wishes
JOIN wish_constraints AS wc ON wc.wish_id = wishes.id 
WHERE wc.type = 'TIME' AND ? <= endTime AND ? >= startTime");
    $stmt->bindValue(1, $params->startTime, SQLITE3_INTEGER);
    $stmt->bindValue(2, $params->endTime, SQLITE3_INTEGER);
    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    $result = array();
    while ($row = $rows->fetchArray()) {
        $row = removeNumericKeys($row);
        array_push($result, (object)$row);
    }
    return ($result);
}

function getAllOrgsAndRepsForTravel($row)
{
    global $dbconn;
    $resultRow = $row;
    //Get all involved organisations
    $stmt = $dbconn->prepare("SELECT o.name from trip_org_presences JOIN organisations o on trip_org_presences.org_id = o.id WHERE trip_org_presences.trip_id = ?");
    $stmt->bindValue(1, $row['travel_id'], SQLITE3_INTEGER);
    $orgrows = $stmt->execute();
    if (!$orgrows) error('Query failed ' . $dbconn->lastErrorMsg());
    $orgs = array();
    while ($org = $orgrows->fetchArray()) {
        $org = removeNumericKeys($org);
        $orgs[] = $org;
    }
    $resultRow['organisations'] = $orgs;

    //Get all involved unep_reps
    $stmt = $dbconn->prepare("SELECT r.email, r.firstName, r.lastName FROM rep_trips rt JOIN unep_reps r on rt.rep_id = r.id where rt.trip_id = ?");
    $stmt->bindValue(1, $row['travel_id'], SQLITE3_INTEGER);
    $reprows = $stmt->execute();
    if (!$reprows) error('Query failed ' . $dbconn->lastErrorMsg());
    $reps = array();
    while ($rep = $reprows->fetchArray()) {
        $rep = removeNumericKeys($rep);
        $reps[] = $rep;
    }
    $resultRow['unep_reps'] = $reps;
    return $resultRow;
}


function getTravelWithinTimeframe($params)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT trips.id AS travel_id, trips.name AS travel_name, locations.city, locations.country, locations.lat, locations.lon, trips.startTime, trips.endTime
FROM trips JOIN locations on trips.loc_id = locations.id WHERE ? <= endTime AND ? >= startTime
");
    $stmt->bindValue(1, $params->startTime, SQLITE3_INTEGER);
    $stmt->bindValue(2, $params->endTime, SQLITE3_INTEGER);
    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    $result = array();
    while ($row = $rows->fetchArray()) {
        $row = removeNumericKeys($row);
        $resultRow = getAllOrgsAndRepsForTravel($row);
        array_push($result, (object)$resultRow);
    }
    return ($result);
}


function getUnepPresencesWithinTimeframe($params)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT unep_presences.name, unep_presences.startTime, 
       unep_presences.endTime, locations.city, locations.country, locations.lat, locations.lon
       FROM unep_presences JOIN locations on unep_presences.loc_id = locations.id
        WHERE ? <= endTime AND ? >= startTime");
    $stmt->bindValue(1, $params->startTime, SQLITE3_INTEGER);
    $stmt->bindValue(2, $params->endTime, SQLITE3_INTEGER);
    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    $result = array();
    while ($row = $rows->fetchArray()) {
        $row = removeNumericKeys($row);
        array_push($result, (object)$row);
    }
    return ($result);
}

function getOrganisationPresencesWithinTimeframe($params)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT p.id, o.name, p.startTime, p.endTime, l.city, l.country, l.lat, l.lon FROM presences p
        JOIN organisations o on p.org_id = o.id
        JOIN locations l on p.loc_id = l.id
        WHERE ? <= endTime AND ? >= startTime");
    $stmt->bindValue(1, $params->startTime, SQLITE3_INTEGER);
    $stmt->bindValue(2, $params->endTime, SQLITE3_INTEGER);
    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    $result = array();
    while ($row = $rows->fetchArray()) {
        $row = removeNumericKeys($row);
        array_push($result, (object)$row);
    }
    return ($result);
}

function getAllTravelFromUser($params)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT email, trips.id as travel_id, trips.name as travel_name, locations.city, locations.country, locations.lat, locations.lon, startTime, endTime  
FROM unep_reps
JOIN rep_trips ON unep_reps.id = rep_trips.rep_id
JOIN trips ON trips.id = rep_trips.trip_id
JOIN locations ON locations.id = trips.loc_id 
WHERE unep_reps.email = ?");
    $stmt->bindValue(1, $params->email, SQLITE3_TEXT);
    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    $result = array();
    while ($row = $rows->fetchArray()) {
        $row = removeNumericKeys($row);
        $resultRow = getAllOrgsAndRepsForTravel($row);
        array_push($result, (object)$resultRow);
    }
    return ($result);
}

function getOrganisationFromId($org_id)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT * from organisations where id = ?");
    $stmt->bindValue(1, $org_id, SQLITE3_INTEGER);
    $rows = $stmt->execute();
    return removeNumericKeys($rows->fetchArray());
}

function getOrganisationIdFromName($org_name)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT * from organisations where name=?");
    $stmt->bindValue(1, $org_name, SQLITE3_TEXT);
    $rows = $stmt->execute();
    $row = $rows->fetchArray();
    return $row['id'];
}

function getLocationFromId($loc_id)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT * from locations WHERE id = ?");
    $stmt->bindValue(1, $loc_id, SQLITE3_INTEGER);
    $rows = $stmt->execute();
    return removeNumericKeys($rows->fetchArray());
}

function getUnepRepFromId($rep_id)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT * from unep_reps where id = ?");
    $stmt->bindValue(1, $rep_id, SQLITE3_INTEGER);
    $rows = $stmt->execute();
    return removeNumericKeys($rows->fetchArray());
}

function getTravelFromId($params)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT * from trips where id = ?");
    $stmt->bindValue(1, $params->travel_id, SQLITE3_INTEGER);
    $rows = $stmt->execute();
    $resultRow = getAllOrgsAndRepsForTravel($rows->fetchArray());
    return $resultRow;
}

function getUnepRepIdFromEmail($email)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT * from unep_reps where email = ?");
    $stmt->bindValue(1, $email, SQLITE3_TEXT);

    $rows = $stmt->execute();
    $row = $rows->fetchArray();
    return $row["id"];
}

function getAllConstraintsFromWish($wishId)
{
    global $dbconn;

    $result = array();
    $result["organisations"] = array();
    $result["times"] = array();
    $result["locations"] = array();

    $stmt = $dbconn->prepare("SELECT * FROM wish_constraints WHERE wish_id=?");
    $stmt->bindValue(1, $wishId);

    $rows = $stmt->execute();

    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    else {
        while ($row = $rows->fetchArray()) {
            $row = removeNumericKeys($row);
            switch ($row['type']) {
                case 'TIME':
                    $timeEntry = array();
                    $timeEntry["startTime"] = $row['startTime'];
                    $timeEntry["endTime"] = $row['endTime'];
                    $result["times"][] = $timeEntry;
                    break;
                case 'ORGANISATION':
                    $orgEntry = getOrganisationFromId($row['org_id']);
                    $result["organisations"][] = $orgEntry;
                    break;
                case 'LOCATION':
                    $locEntry = getLocationFromId($row['loc_id']);
                    $result["locations"][] = $locEntry;
                    break;
                default:
                    error("Invalid type while getting constraints. Type:" . $row['type']);
            }
        }
    }
    return $result;
}

function getAllWishesFromUser($params)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT wishes.id, wishes.name, unep_reps.email FROM wishes
JOIN unep_reps ON wishes.wisher_id = unep_reps.id
WHERE unep_reps.email = ?");
    $stmt->bindValue(1, $params->email, SQLITE3_TEXT);
    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    $result = array();
    while ($row = $rows->fetchArray()) {
        $row = removeNumericKeys($row);
        $rowTemp = $row;
        $rowTemp['constraints'] = getAllConstraintsFromWish($row['id']);
        array_push($result, (object)$rowTemp);
    }
    return ($result);
}

function getWishFromId($wish_id)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT wishes.id, wishes.name, unep_reps.email FROM wishes
JOIN unep_reps ON wishes.wisher_id = unep_reps.id
WHERE wishes.id = ?");
    $stmt->bindValue(1, $wish_id, SQLITE3_TEXT);
    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    $result = array();
    while ($row = $rows->fetchArray()) {
        $row = removeNumericKeys($row);
        $rowTemp = $row;
        $rowTemp['constraints'] = getAllConstraintsFromWish($row['id']);
        array_push($result, (object)$rowTemp);
    }
    return ($result);
}

//function getAllSuggestionsForTravel($params)
//{
//    global $dbconn;
//    $stmt = $dbconn->prepare("SELECT suggestions.id, suggestions.emissions, suggestions.emmission_delta, suggestions.time_wasted, suggestions.score  FROM trips
//JOIN suggestions ON suggestions.trip_id = trips.id
//WHERE trips.id = ? ORDER BY suggestions.score");
//    $stmt->bindValue(1, $params->trip_id, SQLITE3_INTEGER);
//    $rows = $stmt->execute();
//    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
//    $result = array();
//    while ($row = $rows->fetchArray()) {
//        array_push($result, (object)$row);
//    }
//    return ($result);
//}

function getAllSuggestionsFromWish($params)
{
    global $dbconn;
    $stmt = $dbconn->prepare("
SELECT s.id, w.wisher_id, w.name as wish_name, ur.email, ur.firstName, ur.lastName,
s.emissions, s.emmission_delta, s.time_wasted, s.score,
l.city as wishCity, l.country as wishCountry, l.lat as wishLat, l.lon as wishLon, 
s.trips__dep_id as unepTripId, t.name as unepTripName,t.startTime as unepTripStart, t.endTime as unepTripEnd, l5.city as 'unepTripCity', l5.country as 'unepTripCountry', l5.lon as 'unepTripLon', l5.lat as 'unepTripLat',
up.id as unepPresenceId, up.name as unepPresenceName, l4.city as 'unepPresenceCity', l4.country as 'unepPresenceCountry', l4.lon as 'unepPresenceLon', l4.lat as 'unepPresenceLat',
s.trip_org_presences__dep_id as tripOrgPresenceId, o1.name as 'tripOrgName', l2.city as 'tripCity', l2.country as 'tripCountry', l2.lon as 'tripLon', l2.lat as 'tripLat',
s.presences__dep_id as presenceOrgId, o2.name as 'presenceOrgName', l3.city as 'orgCity', l3.country as 'orgCountry', l3.lon as 'orgLon', l3.lat as 'orgLat'
FROM suggestions s
JOIN wishes w ON s.wish_id = w.id
JOIN unep_reps ur ON w.wisher_id = ur.id
LEFT JOIN wish_constraints wc ON wc.wish_id = w.id AND wc.type = 'LOCATION'
LEFT JOIN locations l ON wc.loc_id = l.id
LEFT JOIN trips t ON s.trips__dep_id = t.id
LEFT JOIN locations l5 ON t.loc_id = l5.id
LEFT JOIN unep_presences up ON s.unep_presences__dep_id = up.id
LEFT JOIN locations l4 ON up.loc_id = l4.id
LEFT JOIN trip_org_presences top ON s.trip_org_presences__dep_id = top.id
LEFT JOIN organisations o1 ON top.org_id = o1.id
LEFT JOIN trips t2 ON top.trip_id = t2.id
LEFT JOIN locations l2 ON t2.loc_id = l2.id
LEFT JOIN presences p ON s.presences__dep_id = p.id
LEFT JOIN organisations o2 ON p.org_id = o2.id
LEFT JOIN locations l3 ON p.loc_id = l3.id
WHERE w.id = ?

ORDER BY s.score DESC");
    $stmt->bindValue(1, $params->wish_id, SQLITE3_INTEGER);
    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    $result = array();

    $baseKeys = array("id", "wisher_id", "emissions", "emmision_delta", "time_wasted", "score");
    $locConstraintKeys = array("wishCity", "wishCountry", "wishLat", "wishLon");
    $unepTripKeys = array("unepTripId", "unepTripStart", "unepTripEnd", "unepTripName", "unepTripCity", "unepTripCountry", "unepTripLon", "unepTripLat");
    $unepPresenceKeys = array("unepPresenceId", "unepPresenceName", "unepPresenceCity", "unepPresenceCountry", "unepPresenceLon", "unepPresenceLat");
    $tripOrgKeys = array("tripOrgName", "tripCity", "tripCountry", "tripLon", "tripLat");
    $orgPresenceKeys = array("presenceOrgName", "orgCity", "orgCountry", "orgLon", "orgLat");

    while ($row = $rows->fetchArray()) {
        $row = removeNumericKeys($row);
        $allKeys = $baseKeys;
        if (!is_null($row['wishCity'])) {
            $allKeys = array_merge($allKeys, $locConstraintKeys);
        }
        if (!is_null($row['unepTripId'])) {
            $allKeys = array_merge($allKeys, $unepTripKeys);
        }
        if (!is_null($row['unepPresenceId'])) {
            $allKeys = array_merge($allKeys, $unepPresenceKeys);
        }
        if (!is_null($row['tripOrgPresenceId'])) {
            $allKeys = array_merge($allKeys, $tripOrgKeys);
        }
        if (!is_null($row['presenceOrgId'])) {
            $allKeys = array_merge($allKeys, $orgPresenceKeys);
        }


        //Filter results
        $newRow = array_intersect_key($row, array_flip($allKeys));

        //Rename as appropriate and add more info
        /*if ($usedLoc) {
            $newRow['city'] = $newRow['wishCity'];
            $newRow['country'] = $newRow['wishCountry'];
            $newRow['lon'] = $newRow['wishLon'];
            $newRow['lat'] = $newRow['wishLat'];

            unset($newRow['wishCity']);
            unset($newRow['wishCountry']);
            unset($newRow['wishLon']);
            unset($newRow['wishLat']);
        }

        if ($usedTripOrg) {
            $newRow['city'] = $newRow['tripCity'];
            $newRow['country'] = $newRow['tripCountry'];
            $newRow['lon'] = $newRow['tripLon'];
            $newRow['lat'] = $newRow['tripLat'];

            unset($newRow['tripCity']);
            unset($newRow['tripCountry']);
            unset($newRow['tripLon']);
            unset($newRow['tripLat']);
        }

        if ($usedOrgPresence) {
            $newRow['city'] = $newRow['orgCity'];
            $newRow['country'] = $newRow['orgCountry'];
            $newRow['lon'] = $newRow['orgLon'];
            $newRow['lat'] = $newRow['orgLat'];

            unset($newRow['tripCity']);
            unset($newRow['tripCountry']);
            unset($newRow['tripLon']);
            unset($newRow['tripLat']);
        }*/

        if (key_exists('unepTripId', $newRow)) {
            $newRow['city'] = $newRow['unepTripCity'];
            $newRow['country'] = $newRow['unepTripCountry'];
            $newRow['lon'] = $newRow['unepTripLon'];
            $newRow['lat'] = $newRow['unepTripLat'];

            unset($newRow['unepTripCity']);
            unset($newRow['unepTripCountry']);
            unset($newRow['unepTripLon']);
            unset($newRow['unepTripLat']);
        }

        if (key_exists('unepPresenceId', $newRow)) {
            $newRow['city'] = $newRow['unepPresenceCity'];
            $newRow['country'] = $newRow['unepPresenceCountry'];
            $newRow['lon'] = $newRow['unepPresenceLon'];
            $newRow['lat'] = $newRow['unepPresenceLat'];

            unset($newRow['unepPresenceCity']);
            unset($newRow['unepPresenceCountry']);
            unset($newRow['unepPresenceLon']);
            unset($newRow['unepPresenceLat']);
        }

        //$newRow['involvedReps'] = array();

        if (key_exists('unepTripId', $newRow)) {
//            $stmt2 = $dbconn->prepare("SELECT ur.id, email, firstName, lastName
//            FROM rep_trips
//            JOIN unep_reps ur on rep_trips.rep_id = ur.id WHERE rep_trips.trip_id=?");
//            $stmt2->bindValue(1,$newRow['unepTripId']);
//
//            $rows2 = $stmt2->execute();
//            if (!$rows2) error('Query failed ' . $dbconn->lastErrorMsg());
//
//            while($row2 = $rows2->fetchArray()){
//                $row = removeNumericKeys($row2);
//                $newRow['involvedReps'][] = $row;
//            }

            $newRow['travel_id'] = $newRow['unepTripId'];

            $newRow = getAllOrgsAndRepsForTravel($newRow);

            unset($newRow['travel_id']);

        }

        array_push($result, (object)$newRow);
    }
    return ($result);
}

function getOrCreateLocation($data)
{
    global $dbconn;
    $locStmt = $dbconn->prepare("SELECT id FROM locations WHERE city = ? AND country = ?");
    $locStmt->bindValue(1, $data->city);
    $locStmt->bindValue(2, $data->country);
    $rows = $locStmt->execute();

    while ($row = $rows->fetchArray()) {
        $row = removeNumericKeys($row);
        return $row['id'];
    }

    $newLocStmt = $dbconn->prepare("INSERT INTO locations(lat,lon,city,country) VALUES(?,?,?,?)");
    $newLocStmt->bindValue(1, $data->lat, SQLITE3_FLOAT);
    $newLocStmt->bindValue(2, $data->lon, SQLITE3_FLOAT);
    $newLocStmt->bindValue(3, $data->city, SQLITE3_TEXT);
    $newLocStmt->bindValue(4, $data->country, SQLITE3_TEXT);
    $newLocStmt->execute();
    return $dbconn->lastInsertRowID();

}

function createNewTravel($params)
{
    global $dbconn;

    $locId = getOrCreateLocation($params);
    $rep_id = getUnepRepIdFromEmail($params->email);

    $stmt = $dbconn->prepare("INSERT INTO trips (name,loc_id,startTime,endTime) VALUES(?,?,?,?)");
    $stmt->bindValue(1, $params->name, SQLITE3_TEXT);
    $stmt->bindValue(2, $locId, SQLITE3_INTEGER);
    $stmt->bindValue(3, $params->startTime, SQLITE3_INTEGER);
    $stmt->bindValue(4, $params->endTime, SQLITE3_INTEGER);


    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());

    $trip_id = $dbconn->lastInsertRowID();

    $stmt = $dbconn->prepare("INSERT INTO rep_trips(rep_id,trip_id) VALUES(?,?)");
    $stmt->bindValue(1, $rep_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, $trip_id, SQLITE3_INTEGER);
    $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());

    if ($params->org !== "") {
        $org_id = getOrganisationIdFromName($params->org);
        $stmt = $dbconn->prepare("INSERT INTO trip_org_presences(trip_id, org_id) VALUES(?,?)");
        $stmt->bindValue(1, $trip_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $org_id, SQLITE3_INTEGER);
        $stmt->execute();
        if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    }


    computeMatches("trips", $trip_id);

    return (object)array('outcome' => 'succeeded', 'inserted_id' => $trip_id);
}

function deleteTravelFromId($params)
{
    global $dbconn;
    $stmt = $dbconn->prepare("DELETE FROM trips WHERE id = ?");
    $stmt->bindValue(1, $params->id, SQLITE3_INTEGER);
    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    return (object)array('outcome' => 'succeeded');
}

function createNewWish($name, $email, $time_constraints, $org_constraints, $loc_constraints)
{
    global $dbconn;
    $wisher_id = getUnepRepIdFromEmail($email);

    $stmt = $dbconn->prepare("INSERT INTO wishes(name, wisher_id) VALUES (?,?)");
    $stmt->bindValue(1, $name, SQLITE3_TEXT);
    $stmt->bindValue(2, $wisher_id, SQLITE3_INTEGER);
    $rows = $stmt->execute();

    $wish_id = $dbconn->lastInsertRowID();
    //TIP: LAST_INSERT_ROWID() returns last inserted id in the DB.
    //it's determined on a per-connection basis so no need to worry about concurrency.
    foreach ($time_constraints as $time_constraint) {
        //TODO: insert time constraint using these two limits.
        //error("invalid range") when startTime > endTime.

        $startTime = $time_constraint->startTime;
        $endTime = $time_constraint->endTime;

        if ($endTime < $startTime) {
            error("invalid time range (endTime<startTime");
        }

        $stmt = $dbconn->prepare("INSERT INTO wish_constraints(wish_id,type,startTime,endTime) VALUES (?,'TIME',?,?)");
        $stmt->bindValue(1, $wish_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $startTime, SQLITE3_INTEGER);
        $stmt->bindValue(3, $endTime, SQLITE3_INTEGER);
        $rows = $stmt->execute();
    }

    foreach ($org_constraints as $org_constraint) {
        $stmt = $dbconn->prepare("INSERT INTO wish_constraints(wish_id,type,org_id) VALUES (?,'ORGANISATION',?)");
        $org_id = getOrganisationIdFromName($org_constraint->name);
        $stmt->bindValue(1, $wish_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $org_id, SQLITE3_INTEGER);
        $rows = $stmt->execute();
    }

    foreach ($loc_constraints as $loc_constraint) {
        $stmt = $dbconn->prepare("INSERT INTO wish_constraints(wish_id,type,loc_id) VALUES (?,'LOCATION',?)");
        $loc_id = getOrCreateLocation($loc_constraint);
        $stmt->bindValue(1, $wish_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $loc_id, SQLITE3_INTEGER);
        $rows = $stmt->execute();
    }

    computeMatches("wishes", $wish_id);

    //TODO: similarly for org_constraints, loc_constraints. Content structure is specified in util.js
    return (object)array('outcome' => 'succeeded', 'inserted_id' => $wish_id);
}

function deleteWishFromId($params)
{
    global $dbconn;
    $stmt = $dbconn->prepare("DELETE FROM wishes WHERE id = ?");
    $stmt->bindValue(1, $params->id, SQLITE3_INTEGER);
    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    $result = array();
    while ($row = $rows->fetchArray()) {
        array_push($result, (object)$row);
    }
    return (object)array('outcome' => 'succeeded');
}

function createNewUnepPresence($params)
{
    global $dbconn;

    $locId = getOrCreateLocation($params);

    $stmt = $dbconn->prepare("INSERT INTO unep_presences (name, loc_id,startTime,endTime) VALUES(?,?,?,?)");
    $stmt->bindValue(1, $params->name, SQLITE3_TEXT);
    $stmt->bindValue(2, $locId, SQLITE3_INTEGER);
    $stmt->bindValue(3, $params->startTime, SQLITE3_INTEGER);
    $stmt->bindValue(4, $params->endTime, SQLITE3_INTEGER);


    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());

    $unep_presence_id = $dbconn->lastInsertRowID();

    computeMatches("unep_presences", $unep_presence_id);

    return (object)array('outcome' => 'succeeded', 'inserted_id' => $unep_presence_id);
}

function createNewOrganisationPresence($params)
{
    global $dbconn;

    $org_id = getOrganisationIdFromName($params->orgName);
    $locId = getOrCreateLocation($params);

    $stmt = $dbconn->prepare("INSERT INTO presences (org_id, loc_id,startTime,endTime) VALUES(?,?,?,?)");
    $stmt->bindValue(1, $org_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, $locId, SQLITE3_INTEGER);
    $stmt->bindValue(3, $params->startTime, SQLITE3_INTEGER);
    $stmt->bindValue(4, $params->endTime, SQLITE3_INTEGER);


    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());

    $org_presence_id = $dbconn->lastInsertRowID();

    computeMatches("presences", $org_presence_id);

    return (object)array('outcome' => 'succeeded', 'inserted_id' => $org_presence_id);
}

function getLocationsOfSuggestion($suggestion_id)
{
    global $dbconn;

    $stmt = $dbconn->prepare("SELECT s.id, t.loc_id AS trip_source_id, up.loc_id AS unep_presence_loc_id, wc.loc_id AS wish_loc_id, p.loc_id as org_loc_id, t2.loc_id as trip_dest_id
from suggestions s
JOIN wishes w ON s.wish_id = w.id
LEFT JOIN wish_constraints wc ON wc.wish_id = w.id AND wc.type = 'LOCATION'
LEFT JOIN trips t on s.trips__dep_id = t.id
LEFT JOIN unep_presences up ON s.unep_presences__dep_id = up.id
LEFT JOIN presences p ON s.presences__dep_id = p.id
LEFT JOIN trip_org_presences top ON s.trip_org_presences__dep_id = top.id
LEFT JOIN trips t2 ON top.trip_id = t2.id
where s.id = ?");

    $stmt->bindValue(1, $suggestion_id, SQLITE3_INTEGER);
    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    $row = $rows->fetchArray();

    $result = array();

    if (!is_null($row['trip_source_id'])) {
        $result['src'] = $row['trip_source_id'];
    } else if (!is_null($row['unep_presence_loc_id'])) {
        $result['src'] = $row['unep_presence_loc_id'];
    } else {
        error("src : Corrupt suggestion or suggestion acceptance logic is flawed. Blame Daniel.");
    }

    if (!is_null($row['wish_loc_id'])) {
        $result['dest'] = $row['wish_loc_id'];
    } else if (!is_null($row['org_loc_id'])) {
        $result['dest'] = $row['org_loc_id'];
    } else if (!is_null($row['trip_dest_id'])) {
        $result['dest'] = $row['trip_dest_id'];
    } else {
        error("dest : Corrupt suggestion or suggestion acceptance logic is flawed. Blame Daniel.");
    }

    return $result;
}

function acceptSuggestion($params)
{
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT * from suggestions s JOIN wishes w on s.wish_id = w.id WHERE s.id = ?");
    $stmt->bindValue(1, $params->suggestion_id, SQLITE3_INTEGER);
    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());

    $row = $rows->fetchArray();

    $wish_id = $row['wish_id'];

    //TODO: add match_loc_id (where we're coming from) and wish_loc_id(where we're going to go)
    //this means that technically this is only relevant when were getting matches that are not on a trip

    $locs = getLocationsOfSuggestion($params->suggestion_id);

    $stmt = $dbconn->prepare("INSERT INTO acceptedSuggestions(wisher_id, wish_name, emissions, emission_delta, time_accepted,src_loc_id, dest_loc_id) VALUES(?,?,?,?,?,?,?)");
    $stmt->bindValue(1, $row['wisher_id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $row['name'], SQLITE3_TEXT);
    $stmt->bindValue(3, $row['emissions'], SQLITE3_FLOAT);
    $stmt->bindValue(4, $row['emmission_delta'], SQLITE3_FLOAT);
    $stmt->bindValue(5, time(), SQLITE3_INTEGER);
    $stmt->bindValue(6, $locs['src'], SQLITE3_INTEGER);
    $stmt->bindValue(7, $locs['dest'], SQLITE3_INTEGER);

    $stmt->execute();

    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());

    $stmt = $dbconn->prepare("DELETE FROM wishes WHERE id = ?");
    $stmt->bindValue(1,$wish_id,SQLITE3_INTEGER);
    $rows = $stmt->execute();

    $entry_id = $dbconn->lastInsertRowID();

    updateSuggestion($entry_id);

    // return (object)$row;
    return (object)array('outcome' => 'succeeded', 'inserted_id' => $entry_id);
}

function getTotalEmissionsSaved($params)
{
    global $dbconn;

    $stmt = $dbconn->prepare("SELECT SUM(emission_delta) FROM acceptedSuggestions");
    $row = $stmt->execute()->fetchArray();

    return (object)array('totalEmissionsSaved' => $row['SUM(emission_delta)']);
}

function getEmissionsSavedFromUser($params)
{
    global $dbconn;

    $stmt = $dbconn->prepare("SELECT SUM(emission_delta) 
    FROM acceptedSuggestions 
    JOIN unep_reps ur on acceptedSuggestions.wisher_id = ur.id WHERE ur.email=?");
    $stmt->bindValue(1, $params->email, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray();

    $result = array();

    if (is_null($row['SUM(emission_delta)'])) {
        $result['emissionsSaved'] = 0.0;
    } else {
        $result['emissionsSaved'] = $row['SUM(emission_delta)'];
    }

    return (object)$result;

}

function userExists($params)
{
    global $dbconn;

    $stmt = $dbconn->prepare("SELECT * from unep_reps where email = ?");
    $stmt->bindValue(1, $params->email, SQLITE3_TEXT);
    $rows = $stmt->execute();

    $result = array();
    if ($rows->fetchArray()) {
        $result['exists'] = true;
    } else {
        $result['exists'] = false;
    }

    return (object)$result;
}

function createNewUser($params)
{
    global $dbconn;

    if (userExists($params)->exists === true) {
        $outcome = "exists";
        $unep_rep_id = getUnepRepIdFromEmail($params->email);
    } else {
        $stmt = $dbconn->prepare("INSERT INTO unep_reps(email,firstName,lastName) VALUES(?,?,?)");
        $stmt->bindValue(1, $params->email, SQLITE3_TEXT);
        $stmt->bindValue(2, $params->firstName, SQLITE3_TEXT);
        $stmt->bindValue(3, $params->lastName, SQLITE3_TEXT);
        $rows = $stmt->execute();
        if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
        $unep_rep_id = $dbconn->lastInsertRowID();
        $outcome = "succeeded";
    }

    return (object)array('outcome' => $outcome, 'inserted_id' => $unep_rep_id);
}

function organisationExists($params)
{
    global $dbconn;

    $stmt = $dbconn->prepare("SELECT * from organisations where name = ?");
    $stmt->bindValue(1, $params->name, SQLITE3_TEXT);
    $rows = $stmt->execute();

    $result = array();
    if ($rows->fetchArray()) {
        $result['exists'] = true;
    } else {
        $result['exists'] = false;
    }

    return (object)$result;
}

function createNewOrganisation($params)
{
    global $dbconn;

    if (organisationExists($params)->exists === true) {
        $outcome = "exists";
        $org_id = getOrganisationIdFromName($params->name);
    } else {
        $stmt = $dbconn->prepare("INSERT INTO organisations(name) VALUES (?)");
        $stmt->bindValue(1, $params->name, SQLITE3_TEXT);
        $rows = $stmt->execute();
        if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
        $org_id = $dbconn->lastInsertRowID();
        $outcome = "succeeded";
    }

    return (object)array('outcome' => $outcome, 'inserted_id' => $org_id);
}

function removeOldTravel($params)
{
    global $dbconn;
    $stmt = $dbconn->prepare("DELETE FROM trips WHERE endTime<?");
    $stmt->bindValue(1, time() + 86400, SQLITE3_INTEGER);
    $stmt->execute();
}

function getUserSavingDetails($params){
    global $dbconn;
    $stmt = $dbconn->prepare("SELECT wish_name, emission_delta, 
       l.city as src_city, l.country as src_country, l2.city as dest_city, l2.country as dest_country,
       time_accepted
FROM acceptedSuggestions 
JOIN unep_reps ur on acceptedSuggestions.wisher_id = ur.id
JOIN locations l on acceptedSuggestions.src_loc_id = l.id
JOIN locations l2 on acceptedSuggestions.dest_loc_id = l2.id
WHERE ur.email = ?");
    $stmt->bindValue(1,$params->email,SQLITE3_TEXT);
    $rows = $stmt->execute();
    if (!$rows) error('Query failed ' . $dbconn->lastErrorMsg());
    $result = array();
    while ($row = $rows->fetchArray()) {
        $row = removeNumericKeys($row);
        array_push($result, (object)$row);
    }
    return ($result);


}

function debug1($params)
{
    $stuff = array();
    $stuff['javaRan'] = computeMatches("wishes", 5);
    return (object)$stuff;

}

$request = json_decode($_GET['q']);


switch ($request->method) {
    case 'stub':
        break;

    case 'getAllTables':
        $result = getAllTables($request->params);
        answerJsonAndDie($result);
        break;

    case 'getAllOrganisationNames':
        $result = getAllOrganisationNames($request->params);
        answerJsonAndDie($result);
        break;

    case 'getWishesWithinTimeframe':
        $result = getWishesWithinTimeframe($request->params);
        answerJsonAndDie($result);
        break;

    case 'getTravelWithinTimeframe':
        $result = getTravelWithinTimeframe($request->params);
        answerJsonAndDie($result);
        break;

    case 'getUnepPresencesWithinTimeframe':
        $result = getUnepPresencesWithinTimeframe($request->params);
        answerJsonAndDie($result);
        break;

    case 'getLocationFromId':
        $result = getLocationFromId($request->params->loc_id);
        answerJsonAndDie($result);
        break;

    case 'getOrganisationFromId':
        $result = getOrganisationFromId($request->params->org_id);
        answerJsonAndDie($result);
        break;

    case 'getUnepRepFromId':
        $result = getUnepRepFromId($request->params->rep_id);
        answerJsonAndDie($result);
        break;

    case 'getTravelFromId':
        $result = getTravelFromId($request->params);
        answerJsonAndDie($result);
        break;

    case 'getAllTravelFromUser':
        $result = getAllTravelFromUser($request->params);
        answerJsonAndDie($result);
        break;

    case 'getAllWishesFromUser':
        $result = getAllWishesFromUser($request->params);
        answerJsonAndDie($result);
        break;

    case 'getWishFromId':
        $result = getWishFromId($request->params->wish_id);
        answerJsonAndDie($result);
        break;

//    case 'getAllSuggestionsForTravel':
//        $result = getAllSuggestionsForTravel($request->params);
//        answerJsonAndDie($result);
//        break;

    case 'getAllSuggestionsFromWish':
        $result = getAllSuggestionsFromWish($request->params);
        answerJsonAndDie($result);
        break;

    case 'createNewTravel':
        $result = createNewTravel($request->params);
        answerJsonAndDie($result);
        break;

    case 'deleteTravelFromId':
        $result = deleteTravelFromId($request->params);
        answerJsonAndDie($result);
        break;

    case 'createNewWish':
        $result = createNewWish($request->params->name, $request->params->email, $request->params->timeConstraints, $request->params->orgConstraints, $request->params->locConstraints);
        answerJsonAndDie($result);
        break;

    case 'deleteWishFromId':
        $result = deleteWishFromId($request->params);
        answerJsonAndDie($result);
        break;

    case 'getOrganisationPresencesWithinTimeframe':
        $result = getOrganisationPresencesWithinTimeframe($request->params);
        answerJsonAndDie($result);
        break;

    case 'createNewUnepPresence':
        $result = createNewUnepPresence($request->params);
        answerJsonAndDie($result);
        break;

    case 'createNewOrganisationPresence':
        $result = createNewOrganisationPresence($request->params);
        answerJsonAndDie($result);
        break;

    case 'acceptSuggestion':
        $result = acceptSuggestion($request->params);
        answerJsonAndDie($result);
        break;

    case 'getTotalEmissionsSaved':
        $result = getTotalEmissionsSaved($request->params);
        answerJsonAndDie($result);
        break;

    case 'getEmissionsSavedFromUser':
        $result = getEmissionsSavedFromUser($request->params);
        answerJsonAndDie($result);
        break;

    case 'getUserSavingDetails':
        $result = getUserSavingDetails($request->params);
        answerJsonAndDie($result);
        break;

    case 'userExists':
        $result = userExists($request->params);
        answerJsonAndDie($result);
        break;

    case 'createNewUser':
        $result = createNewUser($request->params);
        answerJsonAndDie($result);
        break;

    case 'organisationExists':
        $result = organisationExists($request->params);
        answerJsonAndDie($result);
        break;

    case 'createNewOrganisation':
        $result = createNewOrganisation($request->params);
        answerJsonAndDie($result);
        break;

    case 'removeOldTravel':
        $result = removeOldTravel($request->params);
        answerJsonAndDie($result);
        break;

    case 'debug1':
        $result = debug1($request->params);
        answerJsonAndDie($result);
        break;

    default:
        error('method: "' . $request->method . '" is not defined');
}
