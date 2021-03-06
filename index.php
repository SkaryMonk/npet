<?php

/* TODO
Kan du lave en top 5 over mænd/kvinder med den højeste individuelle rating?
Heavy relative strength index på tværs af bander... for... _reasons_ - BS+(weapon strength*avg(sustained fire)+((W+T)/2)+(Ld/4)?
*/

include_once("config.php");

try { // OPRET FORBINDELSE
	$conn = new PDO('mysql:host=localhost;dbname='.$database, $username, $password);
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
	echo "Connection failed: " . $e->getMessage();
}	

function format_time($t,$f=':') {
	// t = seconds, f = separator 
	return sprintf("%02d%s%02d%s%02d", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
}

function navngiv($id,$conn) {
	$stmt = $conn->prepare("SELECT medlem FROM gangers WHERE id=$id");
	$stmt->execute();
	$navn = $stmt->fetch();
	return array_pop($navn);
}

function start_tabel($overskrifter) {
	$linje = "<tr>";
	echo "<table style='border: solid 1px black;'>";
	foreach($overskrifter as $overskrift) {
		$linje .= "<th>" . $overskrift . "</th>";
	}
	echo $linje . "</tr>";
}

if (!isset($_POST["season"]) || $_POST["season"]=='alle') {
	$season = "";
	$kampe_i_season = "";
} else {
	$season = " WHERE season = '" . $_POST["season"] . "'";
	try {
		$stmt = $conn->prepare("SELECT id FROM kampe" . $season);
		$stmt->execute();
		$kampe_i_season = $stmt->fetchAll();
		$forste_kamp = $kampe_i_season[0]['id'];
		$sidste_kamp = end($kampe_i_season)['id'];
		$kampe_i_season = " WHERE kamp_id BETWEEN " . $forste_kamp . " AND " . $sidste_kamp;
	} 
	catch(PDOException $e) {
		echo "Error: " . $e->getMessage();
	}
}

echo "Inkludér kun kampe fra sæson: " . $_POST['season'] . "
	<form method='post'>
		<select name='season'>
			<option value='alle'>Alle</option>
			<option>3</option>
			<option>3.5</option>
		</select>
		<input type='submit' />
	</form>\n";

try { // MEST SPILLEDE SCENARIER
    $stmt = $conn->prepare("SELECT ang_id, for_id, scslag, scenario, vinder_id FROM kampe" . $season);
    $stmt->execute();

    // set the resulting array to associative
    $result = $stmt->setFetchMode(PDO::FETCH_ASSOC);
	echo "Mest spillede scenarier";
	start_tabel(array("Antal","Scenario","Vundet af angriber","Vundet af forsvarer"));
	$scenarier = array();
	foreach($stmt->fetchAll() as $udfald) {
		if ($udfald["scenario"] == "Gang Fight" && ($udfald["scslag"] == 5 || $udfald["scslag"] == 6)) {
			$udfald["scenario"] = "Gang Fight (5-6)";
		}
		if ( array_key_exists($udfald["scenario"], $scenarier) ) {
			$scenarier[$udfald["scenario"]]["ialt"]++;
			if ( $udfald["ang_id"] == $udfald["vinder_id"] ) {
				$scenarier[$udfald["scenario"]]["v_a_a"]++;
			} else {
				$scenarier[$udfald["scenario"]]["v_a_f"]++;
			}
		} else {
			$scenarier[$udfald["scenario"]]["ialt"] = 1;
			if ( $udfald["ang_id"] == $udfald["vinder_id"] ) {
				$scenarier[$udfald["scenario"]]["v_a_a"] = 1;
				$scenarier[$udfald["scenario"]]["v_a_f"] = 0;
			} else {
				$scenarier[$udfald["scenario"]]["v_a_f"] = 1;
				$scenarier[$udfald["scenario"]]["v_a_a"] = 0;
			}
		}
	}
	uasort( $scenarier, function($a, $b) {
		return $a["ialt"] <=> $b["ialt"];
	});
	foreach( array_reverse( $scenarier ) as $s=>$c ) {
		echo "<tr>
				<td style='width:150px;border:1px solid black;'>" . $c["ialt"] . "</td>
				<td style='width:150px;border:1px solid black;'>" . $s . "</td>
				<td style='width:150px;border:1px solid black;'>" . $c["v_a_a"] . "</td>
				<td style='width:150px;border:1px solid black;'>" . $c["v_a_f"] . "</td>
			<tr>";
	}
	echo "</table>";
}
catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

try { // MEST SÅRENDE INDIVIDER
    $stmt = $conn->prepare("SELECT saarer_id, COUNT(*) AS occurrences FROM wounds".$kampe_i_season." GROUP BY saarer_id ORDER BY occurrences DESC LIMIT 5");
    $stmt->execute();
    $result = $stmt->setFetchMode(PDO::FETCH_ASSOC);
	echo "Mest sårende individer";
	start_tabel(array("Navn","Antal sår"));
	// print_r($stmt->fetchAll());
	foreach( $stmt->fetchAll() as $rekord ) {
		echo "<tr><td style='width:150px;border:1px solid black;'>" . navngiv($rekord["saarer_id"],$conn) . "</td><td style='width:150px;border:1px solid black;'>" . $rekord["occurrences"] . "</td><tr>";
	}
	echo "</table><br />";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

try { // MEST SÅREDE INDIVIDER
    $stmt = $conn->prepare("SELECT saaret_id, COUNT(*) AS occurrences FROM wounds".$kampe_i_season." GROUP BY saaret_id ORDER BY occurrences DESC LIMIT 5");
    $stmt->execute();
    $result = $stmt->setFetchMode(PDO::FETCH_ASSOC);
	echo "Mest sårede individer";
	start_tabel(array("Navn","Antal sår"));
	// print_r($stmt->fetchAll());
	foreach( $stmt->fetchAll() as $rekord ) {
		echo "<tr><td style='width:150px;border:1px solid black;'>" . navngiv($rekord["saaret_id"],$conn) . "</td><td style='width:150px;border:1px solid black;'>" . $rekord["occurrences"] . "</td><tr>";
	}
	echo "</table><br />";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

try { // KAMPE VUNDET AF HHV. A OG F
	$a_hhv_f = array(
		"a_sejre" => 0,
		"a_varighed" => 0,
		"a_v_ialt" => 0,
		"f_sejre" => 0,
		"f_varighed" => 0,
		"f_v_ialt" =>0);
	$stmt = $conn->prepare("SELECT ang_id, for_id, start, slut, vinder_id FROM kampe" . $season);
	$stmt->execute();
	$kampe = $stmt->fetchAll();
	foreach ( $kampe as $kamp ) {
		if ( $kamp["ang_id"] == $kamp["vinder_id"] ) {
			$a_hhv_f["a_sejre"]++;
			if ( $kamp["slut"] - $kamp["start"] > 0 ) {
				$a_hhv_f["a_varighed"] += $kamp["slut"] - $kamp["start"];
				$a_hhv_f["a_v_ialt"]++;
			}
		} else if ( $kamp["for_id"] == $kamp["vinder_id"] ) {
			$a_hhv_f["f_sejre"]++;
			if ( $kamp["slut"] - $kamp["start"] > 0 ) {
				$a_hhv_f["f_varighed"] += $kamp["slut"] - $kamp["start"];
				$a_hhv_f["f_v_ialt"]++;
			}
		}
	}
	echo "Kampe i alt: " . ( $a_hhv_f["a_sejre"] + $a_hhv_f["f_sejre"] ) . "<br />";
	echo "Kampe vundet af angriber: " . $a_hhv_f["a_sejre"] . "<br />";
	echo "Gennemsnitstid for kampe vundet af angriber: " . format_time( $a_hhv_f["a_varighed"] / $a_hhv_f["a_v_ialt"] ) . "<br />";
	echo "Kampe vundet af forsvarer: " . $a_hhv_f["f_sejre"] . "<br />";
	echo "Gennemsnitstid for kampe vundet af forsvarer: " . format_time( $a_hhv_f["f_varighed"] / $a_hhv_f["f_v_ialt"] ) . "<br />";

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

try { // MEST SÅREDE INDIVIDER
    $stmt = $conn->prepare("SELECT saarer_id, saaret_id, COUNT(*) AS saar FROM wounds".$kampe_i_season." GROUP BY saarer_id, saaret_id ORDER BY `saar` DESC");
    $stmt->execute();
    $result = $stmt->setFetchMode(PDO::FETCH_ASSOC);
	$result = $stmt->fetchAll();
	$rivaler = array();
	$noteret = array();
	$udput = array ();

	foreach (  $result as $hentet ) {
		$keys = array_keys( array_column($result, "saarer_id"), $hentet["saaret_id"] );
		$andresaar = 0;
		foreach ( $keys as $key ) {
			if ( $result[$key]['saaret_id'] == $hentet['saarer_id'] ) {
				$andresaar = $result[$key]['saar'];
			}
		}
		$rivalskab = $hentet["saaret_id"] . "+" . $hentet["saarer_id"];
		$rivaler[$rivalskab] = $hentet["saar"] + $andresaar;
	}
	arsort( $rivaler );

	foreach ( $rivaler as $parterne => $graden ) {
		$parterne = explode( "+", $parterne );
		if ( $i > 5 ) {
			break;
		} else if ( $parterne[0] != $noteret[$parterne[1]] && $parterne[0] != $parterne[1] ) {
			$noteret[$parterne[0]] = $parterne[1];
			$i++;
			$udput[] = array( $parterne[0], $parterne[1], $graden );
		}
	}
	
	echo "<br />Værste rivaler";
	start_tabel( array( "Navn", "Andet navn", "Udvekslede sår" ) );
	foreach( $udput as $rekord ) {
		echo "<tr><td style='width:150px;border:1px solid black;'>" . navngiv( $rekord[0], $conn ) . "</td><td style='width:150px;border:1px solid black;'>" . navngiv( $rekord[1], $conn ) . "</td><td style='width:150px;border:1px solid black;'>" . $rekord[2] . "</td><tr>";
	}
	echo "</table><br />";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$conn = null;

?>