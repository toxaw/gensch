<?php
$input = json_decode(file_get_contents('input.json'), true);

echo '<pre>';

print_r($input);
die();
//print_r($input['groups'][0]['period']);

$gl_i = 0;

$groups = $input['groups'];

$discs = $input['discs'];

$rooms = $input['rooms'];

$noSortSchedulesForGroup = [];

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

	//первое сырое спец. расписание

	$firstSpecialScheduleResult = genSpecialSchedule($weightedSchedule, $caLeDiOf, $group, $firstSchedule['learns_days_offers']['week_position']);

	$firstSpecialSchedule = array_column($firstSpecialScheduleResult, 'day');

	$learnsInDayArr = [];

	foreach ($weightedSchedule as $value) 
	{
		$learnsInDayArr[] = count($value);
	}

	$weightedSpecialSchedule = genWeightedScheduleForGroup($firstSpecialSchedule, count($firstSpecialSchedule), max($learnsInDayArr));
	
	// прикрепили даты к дням для спец рассписания

	foreach ($weightedSpecialSchedule as $key => $value) 
	{
		$weightedSpecialSchedule[$key] = ['date' => [$firstSpecialScheduleResult[$key]['date']], 'day' => $value, 'is_special' => true];
	}

	// первое неотсортированное расписание с входящими датами

	$dateStartWeightedSpecialSchedule = null;

	if(isset($weightedSpecialSchedule[0]))
	{
		$dateStartWeightedSpecialSchedule = $weightedSpecialSchedule[0]['date'];
	}

	$weightedSchedule = genNoSortScheduleWidthDates($weightedSchedule, $dateStartWeightedSpecialSchedule, $group);

	$noSortSchedulesForGroup[] = formatData(['weighted_schedule' => $weightedSchedule, 'special_schedule'=> $weightedSpecialSchedule]);

	//print_r($noSortSchedule);

	//print_r($weightedSchedule);

	//print_r($weightedSpecialSchedule);

	//die();
}

//выставление первичных аудиторий
	//присваиваем аудитории предметам с компами
		//присвоение аудитории единой аудитории повторяющемся парам
			//устранение пересечений аудиторий между группами

$discsKeys = array_column($discs,'id');

foreach ($noSortSchedulesForGroup as $nkey => &$nSoShFoGr) 
{	
	foreach ($nSoShFoGr as $sckey => &$schedule) 
	{
		foreach ($schedule as $key => &$value) 
		{
			$roomBookedAll = [];

			$rangesDay = getRangesDay($value['day']);

			$rangesPosition = 0;
			
			$level = 0;

			$levelBefore = 0;

//global $gl_i;$gl_i++; if($gl_i==80) die(print_r($noSortSchedulesForGroup));
			do
			{
				$rangesItem = itemRanges($rangesDay, $rangesPosition);

				$rangesPosition = $rangesItem['position'];

				$value['day'] = $rangesItem['day'];

				if($levelBefore!=$level && $room_id_booked)
				{
					$roomBookedAll[] = $room_id_booked;
				}

				$levelBefore = $level;

				if($rangesPosition==0)
				{
					$level++;
				}

				$roomBooked = $roomBookedAll;

				foreach ($value['day'] as $learnsNumber => &$learn) 
				{
					$disc = $discs[array_search($learn['disc_id'], $discsKeys)];
					
					$room_id = 0;

					if($disc['is_comp'])
					{
						if(isset($value['day'][$learnsNumber-1]) && ($droom_id = getRoomInExistsLearn($value['day'], $learn['disc_id'], $learnsNumber)))
						{
							$room_id = $droom_id;
						}
						else
						{
							$room_id = getRooms(true, $roomBooked, $rooms);
							
							if($room_id==null)
							{
								error(1);
							}

							$roomBooked[] = $room_id;
						}

						$learn['room_id'] = $room_id;	

						$value['debug_info'] = ['rangesPosition' =>$rangesPosition, 'level'=>$level];
					}
				}
				
				foreach ($value['day'] as $learnsNumber => &$learn) 
				{
					$disc = $discs[array_search($learn['disc_id'], $discsKeys)];

					$room_id = 0;

					//физра спортзал

					if($discs['is_splace'])
					{
						foreach ($rooms as $spvalue) 
						{
							if($spvalue['is_splace'])
							{
								$room_id = $spvalue['id'];

								break;
							}
						}
							$learn['room_id'] = $room_id; 

							$value['debug_info'] = ['rangesPosition' =>0, 'level'=>0];						
					}
					else
					{
						if(!$disc['is_comp'])
						{
							if(isset($value['day'][$learnsNumber-1]) && ($droom_id = getRoomInExistsLearn($value['day'], $learn['disc_id'], $learnsNumber)))
							{
								$room_id = $droom_id;
							}
							else
							{
								$room_id = getRooms(false, $roomBooked, $rooms);

								if($room_id==null)
								{
									error(1);
								}

								$roomBooked[] = $room_id;
							}

							$learn['room_id'] = $room_id; 

							$value['debug_info'] = ['rangesPosition' =>$rangesPosition, 'level'=>$level];
						}
					}	
				}
			}
			while ($room_id_booked = isBookedAmongGroups($noSortSchedulesForGroup, $value['day'], $value['date'], $nkey));

			//прикрутка преподов	
		}
	}
}

print_r($noSortSchedulesForGroup);

die('готово');


//--------------------------------------------------------------------------функции

//вызывается при ошибке

function error($code)
{
	$errArr[1] = 'Нехвата аудиторий.';
	die('Ошибка входных данных! '.$errArr[$code]);
}

//когда несколько одинаковых предметов в одном дне по просто продублируем room_id

function getRoomInExistsLearn($day, $disc_id, $learnsNumber)
{
	foreach ($day as $key => $value) 
	{
		if($value['room_id'] && $value['disc_id']==$disc_id && $key<$learnsNumber)
		{
			return $value['room_id'];
		}
	}
}

//итеровка ранжировки дня

function itemRanges($rangesDay, $rangesPosition)
{
	$i = 0;

	$c = 0;

	foreach ($rangesDay as $value) 
	{
		foreach ($value as $svalue) 
		{
			$c++;
		}
	}

	foreach ($rangesDay as $value) 
	{
		foreach ($value as $svalue) 
		{
			if($i==$rangesPosition)
			{
				return ['day' => $svalue, 'position' => ($c==($i+1))?0:($i+1)];
			}

			$i++;
		}
	}

	$i = 0;

	return ['day' => [], 'position' => 0];
}

//ранжировки дня

function getRangesDay($_day)
{
	$rangesDay = [];

	$day = [];

	foreach ($_day as $value) 
	{
		$day[] = $value;
	}

	$slipsDay = getSlipDay($day);	

	$i = 0;

	foreach ($day as $fvalue) 
	{
		foreach ($slipsDay as $value) 
		{	
			$arr = [];

			$j = 0;			
			
			foreach ($value as $svalue) 
			{
				$arr[$i+$j] = $svalue;

				$j++;
			}	
			
			$rangesDay[$i][] = $arr;
			
		}

		$i++;

		//ограничение

		if($i==4)
		{
			break;
		}
	}

	return $rangesDay;
}

// сдвиг предметов

function getSlipDay($day)
{
	$slipsDay = [];

	$slipsDay[] = $day;

	foreach ($day as $value) 
	{ 
		$slipDay = [];

		for ($i=1; $i < count($day); $i++) 
		{ 
			$slipDay[] = $day[$i];
		}

		if(isset($day[0]))
		{
			$slipDay[] = $day[0];
		}

		$day = $slipDay;

		$slipsDay[] = $day;
	}

	if($slipsDay)
	{
		array_pop($slipsDay);
	}

	return $slipsDay;
}

//получить аудиторию

function getRooms($is_comp, $roomBooked, $rooms)
{
	foreach ($rooms as $value) 
	{
		if($is_comp==$value['is_comp'] && !in_array($value['id'], $roomBooked))
		{
			return $value['id'];
		}
	}

	foreach ($rooms as $value) 
	{
		if(!in_array($value['id'], $roomBooked))
		{
			return $value['id'];
		}
	}	

	return null;
}

// все рассписания, день , текущие даты  - получаем блок или нет

function isBookedAmongGroups($schedules, $day, $dates, $nkey_id)
{
	global $discsKeys;

	foreach ($schedules as $nkey => $nSoShFoGr) 
	{	
		foreach ($nSoShFoGr as $sckey => $schedule) 
		{
			foreach ($schedule as $key => $value) 
			{
				foreach ($day as $learnsNumber => $lvalue) 
				{
					if(isset($value['day'][$learnsNumber])  && $lvalue['room_id'] && $value['day'][$learnsNumber]['room_id'] && $value['day'][$learnsNumber]['room_id']==$lvalue['room_id'] && array_intersect($dates, $value['date']) && $nkey!=$nkey_id)
					{
						/*echo "$nkey_id $nkey<br>";
						print_r($value);
						print_r($dates);
						print_r($day);
						print_r($schedules);
						die();*/
						
						$disc = $discs[array_search($lvalue['disc_id'], $discsKeys)];
						
						if(!$disc['is_splace'])
						{
							return $lvalue['room_id'];
						}
					}	
				}
	
			}
		}
	}

	return false;
}

function formatData($arr)
{
	foreach ($arr as $key => $value) 
	{
		foreach ($value as $skey => $svalue) 
		{
			foreach ($svalue['day'] as $kkey => $kvalue) 
			{
				$arr[$key][$skey]['day'][$kkey] = ['disc_id' => $kvalue, 'room_id' => 0];
			}
		}
	}

	return $arr;
}

//размножение по датам для обычного рассписания

function genNoSortScheduleWidthDates($weightedSchedule, $dateStartWeightedSpecialSchedule, $group)
{
	$periodStart = $group['period']['date_start'];

	$periodEnd = $group['period']['date_end'];

	$format = 'd.m.Y';
	
	$start = DateTime::createFromFormat($format, $periodStart);

	$end = DateTime::createFromFormat($format, $periodEnd);

	$weekPosition = false;

	for (; (int)$start->diff($end)->format('%R%a') >=0 ; $start = $start->add(new DateInterval('P1D'))) 
	{ 
		$weekNumber = $start->format('w');
		
		if(!$weekNumber)
		{
			$weekPosition = !$weekPosition;
		}
		else
		{
			if($dateStartWeightedSpecialSchedule && $start->format($format)==$dateStartWeightedSpecialSchedule)
			{
				return $weightedSchedule;
			}
			
			if(!isset($weightedSchedule[($weekNumber-1)+($weekPosition?6:0)]['day']))
			{
				$weightedSchedule[($weekNumber-1)+($weekPosition?6:0)] = ['day' => $weightedSchedule[($weekNumber-1)+($weekPosition?6:0)]];

				$weightedSchedule[($weekNumber-1)+($weekPosition?6:0)]['is_special'] = false;
			}

			$weightedSchedule[($weekNumber-1)+($weekPosition?6:0)]['date'][] = $start->format($format);		
		}
	}

	return $weightedSchedule;
}

//спец. расписание без переработок; отсчет с конца симместра

function genSpecialSchedule($weightedSchedule, $caLeDiOf, $group, $weekPosition)
{
	$periodStart = $group['period']['date_start'];

	$periodEnd = $group['period']['date_end'];

	$format = 'd.m.Y';
	
	$start = DateTime::createFromFormat($format, $periodStart);

	$end = DateTime::createFromFormat($format, $periodEnd);

	$specialSchedule = [];

	for (; (int)$end->diff($start)->format('%R%a') <=0 ; $end = $end->sub(new DateInterval('P1D'))) 
	{ 
		$weekNumber = $end->format('w');
		
		if(!$weekNumber)
		{
			$weekPosition = !$weekPosition;
		}
		else
		{				
			$day = $weightedSchedule[($weekNumber-1)+($weekPosition?6:0)];
			
			$isConversion = false;

			foreach ($caLeDiOf as $key => $value) 
			{
				if(($keyDay = array_search($key, $day)) && $value<0)
				{
					unset($day[$keyDay]);

					sort($day);

					$caLeDiOf[$key]++;

					$isConversion = true;
				}
			}
			
			$specialSchedule[] = ['date' => $end->format($format) ,'day' => $day];
			
			if(!$isConversion)
			{
				return array_reverse($specialSchedule);
			}
		}
	}
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

function genWeightedScheduleForGroup($schedule, $countDays = 12, $maxLearnInDay = null)
{
	$norm = countNormCountLearns($schedule, $countDays);

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
	
	if($maxLearnInDay)
	{ 
		for ($j=1; $j < count($schedule); $j++) 
		{
			for ($i=1; $i < count($schedule); $i++) 
			{ 
				$beforeDayCount = count($schedule[$i-1]);

				while ($beforeDayCount<$maxLearnInDay && count($schedule[$i])>0) 
				{
					$disc_id = array_shift($schedule[$i]);

					array_unshift($schedule[$i-1], $disc_id);

					$beforeDayCount = count($schedule[$i-1]);		
				}
			}
		}
	}

	//смещение вместе одинаковых пар
	
	foreach ($schedule as $key => $value) 
	{
		sort($value);

	 	$schedule[$key] = $value;
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

function countNormCountLearns($schedule, $countDays)
{
	$counts = [];

	foreach ($schedule as $value) 
	{
		$counts[] = count($value);
	}

	$sum = array_sum($counts);

	$sumMaxMin = (int)($sum/$countDays);

	$countMaxMin = $countDays-($sum-($sumMaxMin*$countDays));

	$sumMaxMax = ($sum%$countDays==0)?0:($sumMaxMin+1);

	$countMaxMax = $countDays-$countMaxMin;

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
		'tw_count' 		=>  $twCount,
		'count' 		=>	$count,
		'week_position' => 	$weekPosition,
		'offers' 		=>	['count' => $offers, 'week' => getWeekOffers($week, $weekCount['w_min'])]
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
