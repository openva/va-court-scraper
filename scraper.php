<?php

# Database information.
define('MYSQL_DSN', 'mysql://username:password@localhost/database');

# Define the PCRE that identifies section references. It is best to do so without using the section
# (§) symbol, since section references are sometimes made without its presence.
define('SECTION_PCRE', '/([[0-9]{1,})([0-9A-Za-z\-\.]{0,3})-([0-9A-Za-z\-\.:]*)([0-9A-Za-z]{1,})/');

# Retrieve the list of decisions.
function fetch_url($url)
{
	if (!isset($url))
	{
		return false;
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	$html = curl_exec($ch);
	curl_close($ch);
	return $html;
}

# Include HTML Purifier, which we use to clean up the code and character sets.
require_once 'htmlpurifier/HTMLPurifier.auto.php';

# Configure the database connection.
require_once 'MDB2.php';
$db =& MDB2::connect(MYSQL_DSN);
$db->setCharset('utf8');

# Get a listing of the last ten court decisions that we already have a record of, which we use to
# recognize when we're entering known territory when querying the court's site. We get the last ten,
# rather than the last one, because multiple decisions can (and do) come down on a given day, and we
# don't want to accidentally wind up with some overlap for that day. So we get a healthy-sized list.
$sql = 'SELECT record_number AS number
		FROM court_decisions
		ORDER BY date DESC
		LIMIT 10';

# Execute the query.
$result =& $db->query($sql);

if (PEAR::isError($result))
{
	echo ($db->getMessage());
	die();
}
else
{
	# Build up an array of the ten decisions that we already know about.
	$known = array();
	while ($record = $result->fetchrow(MDB2_FETCHMODE_OBJECT))
	{
		$known[] = $record->number;
	}
}

# The prefix for the URL where the decisions are stored.
$decision_prefix = 'http://www.courts.state.va.us/opinions/opncavtx/';

# Fire up HTML Purifier.
$purifier = new HTMLPurifier();

# Get the listing of all decisions.
$html = fetch_url('http://www.courts.state.va.us/txtcap.htm');

# Convert the HTML to UTF-8.
iconv('ISO-8859-1', 'UTF-8', $html);

# Extract an array containing all of the basic data points on each decision.
$preg_pattern = '/opncavtx\/([0-9]{7}).txt\">([0-9]{7})<\/A> <b>(.*)<\/b> ([0-9]{2}\/[0-9]{2}\/[0-9]{4})\n<br>(.*)\n\n/';
preg_match_all($preg_pattern, $html, $decisions);

# Hack off the completed matched strings -- we only need the components. Also, hack off the first
# match of the case number, since we've got that data twice.
$decisions = array_slice($decisions, 2);

# Prepare our SQL statement for inserting the court decision.
$prepared_sql = '	INSERT INTO court_decisions
					SET type="appeals", record_number=:record_number, name=:name, date=:date,
					abstract=:abstract, decision=:decision, date_created=now()';
$prepared_types = array('text', 'text', 'date', 'text', 'text');
$decision_insert = $db->prepare($prepared_sql, $prepared_types, MDB2_PREPARE_MANIP);

# Iterate through the matches.
$total = count($decisions[0]);
$i = 0;
while ($i < $total)
{
	# Assemble an array of data, with key names that match the field names in MySQL.
	$case = array();
	$case['record_number'] = $decisions[0][$i];
	$case['name'] = $decisions[1][$i];
	$case['date'] = date('Y-m-d', strtotime($decisions[2][$i]));
	$case['abstract'] = $decisions[3][$i];
	# Get the contents of the decision (in plain text) from the URL
	$case['decision'] = fetch_url($decision_prefix.$case['record_number'].'.txt');
	
	# See if we already have a record of this decision. If we do, then we can safely end this entire
	# script, because we're into a section we already have in the database.
	if (array_search($case['record_number'], $known) !== false)
	{
		exit('Finished with new cases.');
	}
	
	# Find every instance of "Code ##.##" that fits the acceptable format for a state code citation.
	preg_match_all(SECTION_PCRE, $case['decision'], $matches);
	
	# We don't need all of the matches data -- just the first sub-array.
	$matches = $matches[2];

	# We assign the count to a variable because otherwise we're constantly diminishing the count,
	# meaning that we don't process the entire array.
	$total_matches = count($matches);
	for ($j=0; $j<$total_matches; $j++)
	{
		$matches[$j] = trim($matches[$j]);
		
		# Lop off trailing periods, colons, and hyphens.
		if ( (substr($matches[$j], -1) == '.') || (substr($matches[$j], -1) == ':')
			|| (substr($matches[$j], -1) == '-') )
		{
			$matches[$j] = substr($matches[$j], 0, -1);
		}
		
		# If the first character is anything other than a number, it's a false match
		if (!is_numeric($matches[$j]{0}))
		{
			unset($matches[$j]);
		}
		
		# If no period or hyphen is found in this string, it's a false match.
		if ( (strstr($matches[$j], '.') === false) || (strstr($matches[$j], '-') === false) )
		{
			unset($matches[$j]);
		}
	}
	
	# Make unique, but with counts.
	$sections = array_count_values($matches);
	
	unset($matches);
	
	# Clean up the HTML and character set.
	foreach ($case as &$tmp)
	{
		$tmp = $purifier->purify($tmp);
	}
	
	# Walk through $case and slash-escape it.
	$case = array_map('addslashes', $case);
	
	# Get the last decision to use when inserting the section mentions in the below loop.
	$court_decision_id = $db->lastInsertID();
	
	# Insert the section listing only if this section is truly new, if we had no prior record of it
	# in the database. Two rows are affected on an update, but just one on an insert, so we use it
	# here to determine whether it's necessary to insert the listing of laws.
	// THIS ISN'T WORKING. UNDERSTANDABLY. If I understand my own code properly (and I may not),
	// the idea here is to insert the section references only if they're not already in there. This
	// probably ought to be moved up (why are we doing the rest of this work?), but we also need
	// to reconsider how to determine if it's necessary to run these inserts. Maybe they should be
	// changed to REPLACE INTOs? Or maybe they're not necessary at all, what with the SELECT at the
	// outset that should bring everything to a halt if and when we hit something that's already
	// in the database?
	if ($affected_rows == 1)
	{	
		foreach ($sections as $law => $count)
		{
			
			# Assemble our SQL for this insert. This should REALLY be done with a prepared statement,
			# but after hours of wrestling with MDB2's prepared statement functionality, I cannot
			# comprehend why the near-identical prepared statement for inserting the decision works,
			# but this statement fails. The error message is totally useless. So, yeah, this.
			$sql = 'INSERT INTO court_decision_laws
					SET court_decision_id='.$db->escape($court_decision_id).',
					law_section="'.$db->escape($law).'", mentions='.$db->escape($count).',
					date_created=now()';
			
			# Execute the query.
			$result =& $db->exec($sql);
			if (PEAR::isError($result))
			{
				echo '<p>'.$sql.'</p>';
				die($result->getMessage());
			}
			
			echo '.';
		}
	}
	
	# Echo explanatory text.
	echo '<h1>'.$case['name'].'</h1><ul>';
	foreach ($sections as $section => $count)
	{
		echo '<li>'.$section.' ('.$count.')</li>';
	}
	echo '</ul>';
	
	# Move onto the next match.
	$i++;
}

?>