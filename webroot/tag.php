<?PHP 

connect_db();
if(isset($_GET['tag'])){
		$ta = $_GET['ta'];              //Not used anymore
		$org = $_GET['org'];            //Not used anymore
		$citag = $_GET['tag'];          //Name of the Tag
		$tag_id = get_tag_id($citag);	//Get	
		$qry = "SELECT * FROM vapps WHERE citag_id =".$tag_id;
		$results = mysql_query($qry);
		if($row = mysql_fetch_array($results)){
			echo strip_tags($row['vts_name']);
		}else{
			echo "ERROR";	
		}
}elseif(isset($_GET['hostname'])){
		
		$hostname = $_GET['hostname'];
		$qry = "SELECT * FROM vapps WHERE vts_name ='".$hostname."'";
		$results = mysql_query($qry);
		if($row = mysql_fetch_array($results)){
			echo get_tag_name($row['citag_id']);
		}else{
			echo "Cannot find a tag for the hostname ".strtoupper($hostname);	
		}	
}else{
	echo "URL Error, Please check your URL and try again";
}


function connect_db(){
	
	$db = 'cloudportal';
	
	$link = mysql_connect('localhost','root','Shr00t12');
	$select_db = mysql_select_db($db);
	if(!$link){
		die ('Cannot connect to DB. Error: ' . mysql_error());
	}elseif(!$select_db){
		die ('Could not select the Database. Eooro: '. mysql_error());
	}else{
		return true;
	}	
}

function get_tag_id($tag_name){
	
	connect_db();
	$qry = "SELECT * FROM citags WHERE name='".$tag_name."'";
	$results = mysql_query($qry);
	if($row = mysql_fetch_array($results)){
		return $row[id];
	}else{
		return 'error'.mysql_error();
	}
}


function get_tag_name($tag_id){

	connect_db();
	$qry = "SELECT * FROM citags WHERE id='".$tag_id."'";
	$results = mysql_query($qry);

	if($row = mysql_fetch_array($results)){
		return $row['name'];
	}elseif(mysql_error()){
		return 'error'.mysql_error();
	}else{
		return '';
	}

}



?>