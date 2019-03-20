<?php
$input = json_decode(file_get_contents('input.json'), true);

echo '<pre>';

//print_r($input);

$groups = $input['groups'];

$discs = $input['discs'];

foreach ($groups as $group) 
{
	$firstSchedule = genFirstScheduleForGroup($group, $discs);

	$weightedSchedule = genWeightedScheduleForGroup($firstSchedule['schedule']);

	print_r($weightedSchedule);

	print_r($firstSchedule['lerans_discs_offers']);

	print_r($firstSchedule['learns_days_offers']);

	print_r(recalculationLeransDiscsOffers($weightedSchedule, $firstSchedule));

	die();
}

// получаем рассписание для остатков

function genScheduleOffers()
{
	// сначала все остатки сбрасываем в остаточные дни остатки пар

	// уравниваем, то есть - взвешиваем

	// если средне арифметическое постоянного рассписания больше остаточного, то уравниваем его на весь период
}

function recalculationLeransDiscsOffers($weightedSchedule, $firstSchedule)
{
	foreach($firstSchedule['learns_days_offers']['offers']['week'] as $key => $value) 
	{
		if($value[0])
		{
			foreach ($weightedSchedule[$key-1] as $svalue) 
			{
				if(isset($firstSchedule['lerans_discs_offers'][$svalue]))
				{
					$firstSchedule['lerans_discs_offers'][$svalue]--;
				}
			}
		}

		if($value[1])
		{
			foreach ($weightedSchedule[$key+5] as $svalue) 
			{
				if(isset($firstSchedule['lerans_discs_offers'][$svalue]))
				{
					$firstSchedule['lerans_discs_offers'][$svalue]--;
				}
			}
		}
	}
	
	foreach($firstSchedule['lerans_discs_offers'] as $key => $value) 
	{
		if(!$value)
		{
			unset($firstSchedule['lerans_discs_offers'][$key]);
		}
	}

	return $firstSchedule['lerans_discs_offers'];
}

// второе взвешенное рассписание

function genWeightedScheduleForGroup($schedule)
{
	$norm = countNormCountLearns($schedule);

	while (!isNorm($schedule, $norm)) 
	{
		$counts = [];

		foreach ($schedule as $value) 
		{
			$counts[] = count($value);
		}

		$maxCount = max($counts);

		$minCount = min($counts);

		$maxCountsArr = [];

		$minCountsArr = [];

		foreach ($schedule as $key => $value) 
		{
			if($maxCount==count($value))
			{
				$maxCountsArr[] = $key;
			}

			if($minCount==count($value))
			{
				$minCountsArr[] = $key;
			}
		}

		$maxDayKey = $maxCountsArr[rand(0, count($maxCountsArr)-1)];

		$minDayKey = $minCountsArr[rand(0, count($minCountsArr)-1)];

		$disc_id = array_shift($schedule[$maxDayKey]);

		array_unshift($schedule[$minDayKey], $disc_id);	
	}
	
	return $schedule;
}

function isNorm($schedule, $norm)
{
	$count_min = 0;

	$count_max = 0;

	foreach ($schedule as $value) 
	{
		if(count($value)==$norm['sum_min'])
		{
			$count_min++;
		}
		else if (count($value)==$norm['sum_max']) 
		{
			$count_max++;
		}
	}

	return $count_min==$norm['count_min'] && $count_max==$norm['count_max'];
}

function countNormCountLearns($schedule)
{
	$counts = [];

	foreach ($schedule as $value) 
	{
		$counts[] = count($value);
	}

	$sum = array_sum($counts);

	$sumMaxMin = (int)($sum/12);

	$countMaxMin = 12-($sum-($sumMaxMin*12));

	$sumMaxMax = ($sum%12==0)?0:($sumMaxMin+1);

	$countMaxMax = 12-$countMaxMin;

	return [
		'sum_min' 	=>	$sumMaxMin,
		'count_min' =>	$countMaxMin,
		'sum_max' 	=>	$sumMaxMax,
		'count_max' =>	$countMaxMax
		];
}

//первое сырое рассписание

function genFirstScheduleForGroup($group, $discs)
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

	//возрващаем эскиз расписания группы и остатки по дням и по предметам в количестве пар

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

	return ['schedule'=> $firstSchedule, 'lerans_discs_offers'=> $leransDiscsOffers, 'learns_days_offers' => $weekResult];
}

function getFullTwoWeek($periodStart, $periodEnd)
{
	$week = ['1'=>[0,0],'2'=>[0,0],'3'=>[0,0],'4'=>[0,0],'5'=>[0,0],'6'=>[0,0]];

	$format = 'd.m.Y';
	
	$start = DateTime::createFromFormat($format, $periodStart);

	$end = DateTime::createFromFormat($format, $periodEnd);

	$weekPosition = false;

	for (; (int)$start->diff($end)->format('%R%a') >=0 ; $start = $start->add(new DateInterval('P1D'))) 
	{ 
		$weekNumber = $start->format('w');
		
		if($weekNumber)
		{
			$week[$weekNumber][$weekPosition?0:1]++;
		}
		else
		{
			$weekPosition = !$weekPosition;
		}
	}

	$weekCount = weekMin($week);

	$count = weekSum($week);
	
	$tw_weekCount = $weekCount['tw_min'];

	$twCount = (int)($tw_weekCount/2);

	$offers = $count-($twCount*6*2);

	return 
	[
		'tw_count' 	=>  $twCount,
		'count' 	=>	$count,
		'offers' 	=>	['count' => $offers, 'week' => getWeekOffers($week, $weekCount['w_min'])]
	];
}

function getWeekOffers($week, $t_min)
{
	foreach ($week as $key => $value) 
	{
		$nvalue = [];

		foreach ($value as $skey => $svalue) 
		{
			$nvalue[$skey] = $svalue-$t_min;
		}

		$week[$key] = $nvalue;
	}

	return $week;
}

function weekMin($week)
{
	$arr = [];

	$min = [];

	foreach ($week as $value) 
	{
		$sum = 0;
		
		foreach ($value as $svalue) 
		{
			$sum += $svalue;
		}

		$arr[] = $sum;

		$min[] = min($value);
	}

	return ['tw_min' => min($arr), 'w_min' => min($min)];
}

function weekSum($week)
{
	$arr = [];

	foreach ($week as $value) 
	{
		$sum = 0;
		
		foreach ($value as $svalue) 
		{
			$sum += $svalue;
		}

		$arr[] = $sum;
	}

	return array_sum($arr);
}

/*
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
		'count' 	=>	$count,
		'offers' 	=>	$offers,
	];
}
*/
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