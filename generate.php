<?php
$input = json_decode(file_get_contents('input.json'), true);

echo '<pre>';

//print_r($input);

$groups = $input['groups'];

$discs = $input['discs'];

foreach ($groups as $group) 
{
	//получили сверх сырое расписание

	$firstSchedule = genFirstScheduleForGroup($group, $discs);

	//получили первое взвещенное расписание

	$weightedSchedule = genWeightedScheduleForGroup($firstSchedule['schedule']);

	$recalculationResult = recalculationSchedule($weightedSchedule, $firstSchedule, $discs, $group['discs']);

	//получили перерасчетное расписание

	$weightedSchedule = $recalculationResult['weighted_schedule'];

	//получили переработки по предметам в количестве пар

	$caLeDiOf = $recalculationResult['ca_le_di_of'];

	die();
}

//спец. расписание без переработок; отсчет с конца симместра

function genSpecialSchedule($weightedSchedule, $group)
{

}

//перерасчет расписания

function recalculationSchedule($weightedSchedule, $firstSchedule, $discs, $groupDiscs)
{
	foreach ($groupDiscs as $value) 
	{			
		do
		{
			$weightedScheduleBefore = $weightedSchedule;

			//отняли 1 предмет

			$weightedSchedule = removeOneLearns($weightedSchedule, $value);

			//уравновесили
			
			$weightedSchedule = genWeightedScheduleForGroup($weightedSchedule);

			//получили переработки

			$caLeDiOf = calculationLeransDiscsOffers($weightedSchedule, $firstSchedule, $discs, $groupDiscs);

			if(!isset($caLeDiOf[$value]))
			{
				break;
			}
			else if ($caLeDiOf[$value]>0)
			{
				$weightedSchedule = $weightedScheduleBefore;

				break;
			}
		}
		while (true);
	}

	return ['weighted_schedule'=>$weightedSchedule, 'ca_le_di_of' => calculationLeransDiscsOffers($weightedSchedule, $firstSchedule, $discs, $groupDiscs)];
}

function removeOneLearns($weightedSchedule, $disc_id)
{
	foreach ($weightedSchedule as $key => $value) 
	{
		foreach ($value as $skey => $svalue) 
		{
			if($svalue==$disc_id)
			{
				unset($weightedSchedule[$key][$skey]);

				foreach ($weightedSchedule as $kkey => $kvalue) 
				{
					sort($kvalue);

					$weightedSchedule[$kkey] = $kvalue;
				}

				return $weightedSchedule;
			}
		}
	}
}

//расчет исчерпывающих часов пар по предметам

function calculationLeransDiscsOffers($weightedSchedule, $firstSchedule, $discs, $groupDiscs)
{
	//reformat discs

	$reformatDiscs = [];

	foreach ($discs as $key => $value) 
	{
		if(in_array($value['id'], $groupDiscs))
		{
			$reformatDiscs[$value['id']] = $value['time']/2;
		}
	}

	for ($i=0; $i < $firstSchedule['learns_days_offers']['tw_count']; $i++) 
	{ 
		foreach ($weightedSchedule as $value) 
		{
			foreach ($value as $svalue) 
			{
				if(isset($reformatDiscs[$svalue]))
				{
					$reformatDiscs[$svalue]--;
				}
			}
		}		
	}

	foreach($firstSchedule['learns_days_offers']['offers']['week'] as $key => $value) 
	{
		if($value[0])
		{
			foreach ($weightedSchedule[$key-1] as $svalue) 
			{
				if(isset($reformatDiscs[$svalue]))
				{
					$reformatDiscs[$svalue]--;
				}
			}
		}

		if($value[1])
		{
			foreach ($weightedSchedule[$key+5] as $svalue) 
			{
				if(isset($reformatDiscs[$svalue]))
				{
					$reformatDiscs[$svalue]--;
				}
			}
		}
	}
	
	foreach($reformatDiscs as $key => $value) 
	{
		if(!$value)
		{
			unset($reformatDiscs[$key]);
		}
	}

	return $reformatDiscs;
}

// второе взвешенное расписание

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

//первое сырое расписание

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

	foreach ($groupDiscs as $value) 
	{
		$learns_moda = $value['learns_moda'];
		
		if($learns_moda)
		{
			if($learns_moda['moda']>0)
			{
				for ($i=0; $i < count($firstSchedule); $i++) 
				{ 
					for ($j=0; $j < $learns_moda; $j++) 
					{ 
						$firstSchedule[$i][] = $value['id'];
					}
				}
			}
			else
			{
				for ($i=0; $i < count($firstSchedule); $i++) 
				{ 	 
					if(($i+1)%abs($learns_moda)==0)
					{
						$firstSchedule[$i][] = $value['id'];
					}
				}
			}

			$leransDiscsOffers[$value['id']] = $learns_moda['offers'];
		}
	}

	return ['schedule'=> $firstSchedule, 'learns_days_offers' => $weekResult];
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

		foreach ($arrDiv as $key => $value) 
		{
			if($value>$learnsCount)
			{
				$moda = -1*$key;

				return $moda;
			}

		}
	}
	else
	{
		$moda = ceil($learnsCount/$daysCount);

		return $moda;
	}
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
