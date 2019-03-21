<?php

$groupCount = 10;

$discToGroupCount = 15;

$roomCount = 30;

$discCount = 50;

$ticherCount = 20;

$periodStart = '12.01.2019';

$periodEnd = '10.06.2019';

//$periodEnd = '10.03.2019';

echo "<pre>";

$groups = [];

$rooms = [];

$discs = [];

$tichers = [];

for($i=0;$i<$discCount;$i++)
{
	$discs[] = ['name' => 'Предмет ' . ($i+1), 'is_splace'=>0, 'is_comp' => rand(0,1) ,'time' => rand(20,80)*2, 'id' => genId(array_column($discs, 'id'))];
}

$discs[] = ['name' => 'Физ-ра', 'is_splace'=>1, 'is_comp' => 0 ,'time' => rand(20,80)*2, 'id' => genId(array_column($discs, 'id'))];

for($i=0;$i<$groupCount;$i++)
{
	$groups[] = ['name' => 'Группа ' . ($i+1), 'discs' => getDiscs(array_column($discs, 'id'), array_column($groups, 'discs'), $discToGroupCount), 'id' => genId(array_column($groups, 'id')), 'period' => getPeriod($periodStart , $periodEnd)];
}

for($i=0;$i<$roomCount;$i++)
{
	$rooms[] = ['name' => 'Аудитория ' . ($i+1), 'is_comp' => rand(0,1) , 'id' => genId(array_column($rooms, 'id'))];
}

$rooms[] = ['name' => 'Спортзал' ,'is_splace'=>0, 'is_comp' => 0 , 'id' => genId(array_column($rooms, 'id'))];

for($i=0;$i<$ticherCount;$i++)
{
	$tichers[] = ['name' => 'Преподаватель ' . ($i+1) , 'id' => genId(array_column($tichers, 'id')),'discs' => getDiscs(array_column($discs, 'id'), array_column($tichers, 'discs'), rand(1,4)), 'id' => genId(array_column($groups, 'id'))];
}

function genId($notArr)
{
	$id = 0;

	do 
	{
		$id = rand(1, 999999);
	} 
	while (in_array($id, $notArr));

	return $id;
}

function getDiscs($discs, $groups, $discToGroupCount)
{
	$discsBooked = [];

	$discsNotBooked = [];

	foreach ($groups as $group) 
	{
		foreach ($group as $value) 
		{
			$discsBooked[$value] = $value;
		}
	}

	foreach ($discs as $value) 
	{
		if(!in_array($value, $discsBooked))
		{
			$discsNotBooked[$value] = $value;  
		}
	}

	$discsNotBooked = randRange($discsNotBooked);

	$discsBooked = randRange($discsBooked);

	$result = [];

	$i = 0;

	foreach ($discsNotBooked as $value) 
	{
		$i++;

		$result[] = $value;

		if($i==$discToGroupCount)
		{
			break;
		}

	}
	
	if($i<$discToGroupCount)
	{
		foreach ($discsBooked as $value) 
		{
			$i++;

			$result[] = $value;

			if($i==$discToGroupCount)
			{
				break;
			}

		}
	}	

	return $result;
}

function randRange($arr)
{
	$arr1 = [];

	foreach ($arr as $value) 
	{
		$arr1[] = $value;
	}

	$result = [];

	$r = 0;

	foreach ($arr1 as $value) 
	{
		do 
		{
			$r = rand(0, count($arr1)-1);
		} 
		while (in_array($arr1[$r], $result));

		$result[$arr1[$r]] = $arr1[$r];
	}	

	return $result;
}

function getPeriod($periodStart , $periodEnd)
{
	$format = 'd.m.Y';
	
	$dateStart = DateTime::createFromFormat($format, $periodStart);

	$dateEnd = DateTime::createFromFormat($format, $periodEnd);

//$interval = $dateStart->diff($dateEnd);

//echo $interval->format('%a');die();

	return ['date_start' =>	$dateStart->add(new DateInterval('P'.rand(0,20).'D'))->format($format),
			'date_end' 	 =>	$dateEnd->sub(new DateInterval('P'.rand(0,20).'D'))->format($format)
			];
}


//print_r($groups);

//print_r($rooms);

//print_r($discs);

//print_r($tichers);

$resultReturn['groups'] = $groups;

$resultReturn['rooms'] = $rooms;

$resultReturn['discs'] = $discs;

$resultReturn['tichers'] = $tichers;

file_put_contents('input.json', json_encode($resultReturn));

die('Входные данные готовы');