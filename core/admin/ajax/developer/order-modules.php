<?
	parse_str($_POST["sort"]);
	$max = count($row);
	
	foreach ($row as $pos => $id) {
		$admin->setModulePosition($id,$max - $pos);
	}
?>