<?php
$input = json_decode(file_get_contents('input.json'), true);

echo '<pre>';

//print_r($input);

$groups = $input['groups'];

$discs = $input['discs'];

print_r(genForGroup($groups[0], $discs));

function genForGroup($group, $discs)
{
	$periodStart = $group['period']['date_start'];

	$periodEnd = $group['period']['date_end'];

	$groupDiscs = []; 

	$weekResult = getFullTwoWeek($periodStart, $periodEnd);
	
	foreach ($group['discs'] as $disc) 
	{
		$discTime = $discs[array_search($disc, array_column($discs, 'id'))]['time'];

		$groupDiscs [] = ['id' => $disc, 'learns_count' =>$discTime/2, 'learns_moda' => getModaLearnToWeeks($weekResult['tw_count'], $discTime/2)];
	}

	//возрвазаем эскиз расписания группы и остатки по дням и по предметам в количестве пар

	$firstSchedule = [[],[],[],[],[],[],[],[],[],[],[],[]];

	$leransDiscsOffers = [];

	foreach ($groupDiscs as $value) 
	{
		$learns_moda = $value['learns_moda'];
		
		if($learns_moda)
		{
			if($learns_moda['moda']>0)
			{
				for ($i=0; $i < count($firstSchedule); $i++) 
				{ 
					for ($j=0; $j < $learns_moda['moda']; $j++) 
					{ 
						$firstSchedule[$i][] = $value['id'];
					}
				}
			}
			else
			{
				for ($i=0; $i < count($firstSchedule); $i++) 
				{ 	 
					if(($i+1)%abs($learns_moda['moda']))
					{
						$firstSchedule[$i][] = $value['id'];
					}
				}
			}

			if($learns_moda['offers'])
			{
				$leransDiscsOffers[$value['id']] = $learns_moda['offers'];
			}
		}
		else
		{
			$leransDiscsOffers[$value['id']] = $value['learns_count'];
		}

	}

	return ['first_schedule'=> $firstSchedule, 'lerans_discs_offers'=> $leransDiscsOffers];
//print_r($discs);
	//print_r($groupDiscs);
//	print_r($weekResult);
	//print_r($group);
}

function getFullTwoWeek($periodStart, $periodEnd)
{
	$week = ['1'=>0,'2'=>0,'3'=>0,'4'=>0,'5'=>0,'6'=>0];

	$format = 'd.m.Y';
	
	$start = DateTime::createFromFormat($format, $periodStart);

	$end = DateTime::createFromFormat($format, $periodEnd);

	for (; (int)$start->diff($end)->format('%R%a') >=0 ; $start = $start->add(new DateInterval('P1D'))) 
	{ 
		$weekNumber = $start->format('w');
		
		if($weekNumber)
		{
			$week[$weekNumber]++;
		}
	}

	$weekCount = min($week);

	$count = array_sum($week);

	$twCount = (int)($weekCount/2);

	$offers = $count-($twCount*6*2);

	return 
	[
		'tw_count' 	=>  $twCount,
		'offers' 	=>	$count,
		'count' 	=>	$offers,
	];
}

function getModaLearnToWeeks($twCount, $learnsCount)
{
	$daysCount = $twCount*2*6;

	if ($learnsCount<$daysCount)
	{
		$i=1;

		$arrDiv = [];

		while ($i<$daysCount) 
		{
			if($daysCount%$i==0)
			{
				$arrDiv[$i] = $daysCount/$i;
			}

			$i++;
		}

		$arrDiv = array_reverse($arrDiv, true);

		$minLast = 0;

		foreach ($arrDiv as $key => $value) 
		{
			if($value>$learnsCount)
			{
				break;
			}

			$minLast = ['moda' => -1*$key, 'learns_count' => $value, 'offers' => $learnsCount-$value];
		}

		return $minLast;
	}
	else
	{
		$moda = (int)($learnsCount/$daysCount);

		$learns_count = $moda*$daysCount; 

		return ['moda' => $moda,'learns_count' => $learns_count, 'offers'=> $learnsCount-$learns_count];
	}
}