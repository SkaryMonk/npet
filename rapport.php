<?php

print_r($_POST);

include_once("config.php");

try { // OPRET FORBINDELSE
	$conn = new PDO("mysql:host=localhost;dbname=".$database, $username, $password);
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
	echo "Connection failed: " . $e->getMessage();
}

$dato = explode( "-", $_POST["dato"] );
$start = explode( ":", $_POST["start"] );
$slut = explode( ":", $_POST["slut"] );
$sta = mktime( $start[0], $start[1], 0, $dato[1], $dato[0], $dato[2] );
$slu = mktime( $slut[0], $slut[1], 0, $dato[1], $dato[0], $dato[2] );
$blind = ( $_POST["blind"] == 'on' ) ? 1 : 0;
$grudge = ( $_POST["grudge"] == 'on' ) ? 1 : 0;

if ( $_POST["indsendt"] == "indsendt" ) {
	try {
		$conn->beginTransaction();
		$query = 'INSERT INTO kampe (ang_id, ang_rating, for_id, for_rating, start, slut, scslag, scenario, blind, grudge, ang_start, ang_total, for_start, for_total, wounds, vinder_id, ture_hardcode, season)
					VALUES ( "'.$_POST["angriber"].'", '.$_POST["angriber_rating"].', "'.$_POST["forsvarer"].'", '.$_POST["forsvarer_rating"].', '.$sta.', '.$slu.', '.$_POST["scslag"].', "'.$_POST["scenario"].'", '.$blind.', '.$grudge.', '.$_POST["a_i_s"].', '.$_POST["a_i_i"].', '.$_POST["f_i_s"].', '.$_POST["f_i_i"].', '.$_GET["wounds"].', '.$_POST["vinder"].', 0, 3 )';
		$conn->exec( $query );
		$kamp_id = $conn->lastInsertId();

		foreach ( $_POST["wound"] as $saar ) {
			$hi = ( $saar["hi"] == 'on' ) ? 1 : 0;
			$query = 'INSERT INTO wounds (kamp_id, tur, saarer_id, saaret_id, high_impact, resultat, serious_injury, multiple_injuries)
						VALUES ('.$kamp_id.', '.$saar["tur"].', '.$saar["saarer"].', '.$saar["saaret"].', '.$hi.', "'.$saar["resultat"].'", '.$saar["si"].', 0)';
			$conn->exec( $query );
		}
		
		$conn->commit();
	} catch (Exception $e) {
		$conn->rollBack();
		echo "Failed: " . $e->getMessage();
	}
}

try {
	$bander = $conn->prepare("SELECT * FROM bander");
	$bander->execute();
	foreach( $bander->fetchAll() as $bande ) {
		$bande_options .= '<option value="' . $bande["id"] . '">' . $bande["navn"] . '</option>\n';
	}
} catch(PDOException $e) {
	echo "Fetch failed: " . $e->getMessage();
}

try {
	$gangers = $conn->prepare("SELECT * FROM gangers ORDER BY medlem ASC");
	$gangers->execute();
	foreach( $gangers->fetchAll() as $ganger ) {
		$ganger_options .= '<option value="' . $ganger["id"] . '">' . $ganger["medlem"] . '</option>\n';
	}
} catch(PDOException $e) {
	echo "Fetch failed: " . $e->getMessage();
}

try {
	$skader = $conn->prepare("SELECT * FROM skader ORDER BY skade ASC");
	$skader->execute();
	foreach( $skader->fetchAll() as $skade ) {
		$skader_options .= '<option value="' . $skade["id"] . '">' . $skade["skade"] . '</option>\n';
	}
} catch(PDOException $e) {
	echo "Fetch failed: " . $e->getMessage();
}

print <<<EOT
<form method="post">
	Angriber: <select name="angriber">$bande_options</select> Rating: <input type="text" name="angriber_rating"><br />
	Forsvarer: <select name="forsvarer">$bande_options</select> Rating: <input type="text" name="forsvarer_rating"><br />
	Dato: <input type="date" name="dato"> Start: <input type="time" name="start"> Slut: <input type="time" name="slut"><br />
	Scenarieslag: <input type="text" name="scslag"> Scenario: <input type="text" name="scenario"><br/> Blind: <input type="checkbox" name="blind"> Grudge: <input type="checkbox" name="grudge"><br />
	Angribers indsatte( Start: <input type="text" name="a_i_s"> I alt: <input type="text" name="a_i_i">) 
	Forsvarers indsatte( Start: <input type="text" name="f_i_s"> I alt: <input type="text" name="f_i_i">)
	<table style='border: solid 1px black;'>
		<tr>
			<th>Tur #</th>
			<th>Sårer</th>
			<th>HI?</th>
			<th>Såret</th>
			<th>Resultat</th>
			<th>SI</th>
		</tr>
EOT;

for ( $w = 0; $w < $_GET["wounds"]; $w++ ) {
	echo( "<tr>
		<th><input type='text' name='wound[".$w."][tur]'></th>
		<th>
			<select name='wound[".$w."][saarer]'>
				".$ganger_options."
			</select>
		</th>
		<th><input type='checkbox' name='wound[".$w."][hi]'></th>
		<th>
			<select name='wound[".$w."][saaret]'>
				".$ganger_options."
			</select>
		</th>
		<th><select name='wound[".$w."][resultat]'>
			<option>W</option>
			<option>FW</option>
			<option>D</option>
			<option>OOA</option>
			</select>
		</th>
		<th>
			<select name='wound[".$w."][si]'>
				".$skader_options."
			</select>
		</th>
		</tr>"
	);
}

print <<<EOT
	</table>
	Vinder: <select name="vinder">$bande_options</select>
	<input type="submit" name="indsendt" value="indsendt">
</form>
EOT;

?>