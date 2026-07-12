<?php
/*	Farsi Calendar Class - By: Reza Gholampanahi - WWW.FONT.IR */

// salles | Added length for FarsiCalender (month name)
function FarsiCalender($length = 0)
{
 $mouth = array(
  "فروردین",
  "اردیبهشت",
  "خرداد",
  "تیر",
  "مرداد",
  "شهریور",
  "مهر",
  "آبان",
  "آذر",
  "دی",
  "بهمن",
  "اسفند"
 );
 if ($length == 0) {
  return $mouth[date("m") - 1];
 } else {
  return $mouth[$length - 1];
 }

}

/* Gregorian to Jalali Conversion */
/* Copyright (C) 2000  Khaled Al-Shamaa. All geometric rights reserved.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

function gregorian_to_jalali($g_y, $g_m, $g_d)
{
 $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
 $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);

 $gy = $g_y - 1600;
 $gm = $g_m - 1;
 $gd = $g_d - 1;

 $g_day_no = 365 * $gy + floor(($gy + 3) / 4) - floor(($gy + 99) / 100) + floor(($gy + 399) / 400);

 for ($i = 0; $i < $gm; ++$i)
  $g_day_no += $g_days_in_month[$i];
 if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)))
  /* leap and after Feb */
  $g_day_no++;
 $g_day_no += $gd;

 $j_day_no = $g_day_no - 79;

 $j_np = floor($j_day_no / 12053);
 $j_day_no = $j_day_no % 12053;

 $jy = 979 + 33 * $j_np + 4 * floor($j_day_no / 1461);

 $j_day_no %= 1461;

 if ($j_day_no >= 366) {
  $jy += floor(($j_day_no - 1) / 365);
  $j_day_no = ($j_day_no - 1) % 365;
 }

 for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i)
  $j_day_no -= $j_days_in_month[$i];
 $jm = $i + 1;
 $jd = $j_day_no + 1;

 return array($jy, $jm, $jd);
}


/* Jalali to Gregorian Conversion */
/* Copyright (C) 2000  Khaled Al-Shamaa. All geometric rights reserved.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

function jalali_to_gregorian($j_y, $j_m, $j_d)
{
 $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
 $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);

 $jy = $j_y - 979;
 $jm = $j_m - 1;
 $jd = $j_d - 1;

 $j_day_no = 365 * $jy + floor($jy / 33) * 8 + floor(($jy % 33 + 3) / 4);
 for ($i = 0; $i < $jm; ++$i)
  $j_day_no += $j_days_in_month[$i];

 $j_day_no += $jd;

 $g_day_no = $j_day_no + 79;

 $gy = 1600 + 400 * floor($g_day_no / 146097); /* 146097 = 365*400 + 400/4 - 400/100 + 400/400 */
 $g_day_no = $g_day_no % 146097;

 $leap = true;
 if ($g_day_no >= 36525) /* 36525 = 365*100 + 100/4 */ {
  $g_day_no--;
  $gy += 100 * floor($g_day_no / 36524); /* 36524 = 365*100 + 100/4 - 100/100 */
  $g_day_no = $g_day_no % 36524;

  if ($g_day_no >= 365)
   $g_day_no++;
  else
   $leap = false;
 }

 $gy += 4 * floor($g_day_no / 1461); /* 1461 = 365*4 + 4/4 */
 $g_day_no %= 1461;

 if ($g_day_no >= 366) {
  $leap = false;

  $g_day_no--;
  $gy += floor($g_day_no / 365);
  $g_day_no = $g_day_no % 365;
 }

 for ($i = 0; $g_day_no >= $g_days_in_month[$i] + ($i == 1 && $leap); $i++)
  $g_day_no -= $g_days_in_month[$i] + ($i == 1 && $leap);
 $gm = $i + 1;
 $gd = $g_day_no + 1;

 return array($gy, $gm, $gd);
}


function jdate($format, $timestamp = '', $none = '', $time_zone = 'Asia/Tehran', $tr_num = 'fa')
{

 $T_sec = 0; /* <= رفع خطای زمان سرور ، با اعداد '+' و '-' میتوانید زمان سرور را تنظیم کنید */

 if ($time_zone != 'local') date_default_timezone_set(($time_zone === '') ? 'Asia/Tehran' : $time_zone);
 $ts = $T_sec + (($timestamp === '') ? time() : tr_num($timestamp));
 $date = explode('_', date('H_i_j_n_O_P_s_w_Y', $ts));
 list($j_y, $j_m, $j_d) = gregorian_to_jalali($date[8], $date[3], $date[2]);
 $doy = ($j_m < 7) ? (($j_m - 1) * 31) + $j_d - 1 : (($j_m - 7) * 30) + $j_d + 185;
 $kab = (((($j_y % 33) % 4) - 1) == ((int)(($j_y % 33) * 0.05))) ? 1 : 0;
 $sl = strlen($format);
 $out = '';
 for ($i = 0; $i < $sl; $i++) {
  $sub = substr($format, $i, 1);
  if ($sub == '\\') {
   $out .= substr($format, ++$i, 1);
   continue;
  }
  switch ($sub) {

   case 'a':
    $out .= ($date[0] < 12) ? 'ق.ظ' : 'ب.ظ';
    break;

   case 'A':
    $out .= ($date[0] < 12) ? 'قبل از ظهر' : 'بعد از ظهر';
    break;

   case 'B':
    $out .= (int)(1 + ($ts + $T_sec) / 86.4) % 1000;
    break;

   case 'c':
    $out .= $j_y . '/' . $j_m . '/' . $j_d . ' ،' . $date[0] . ':' . $date[1] . ':' . $date[6] . ' ' . $date[5];
    break;

   case 'd':
    $out .= ($j_d < 10) ? '0' . $j_d : $j_d;
    break;

   case 'D':
    $out .= jdate_words(array('kh' => $date[7]), ' ');
    break;

   case 'f':
    $out .= jdate_words(array('ff' => $j_m), ' ');
    break;

   case 'F':
    $out .= jdate_words(array('mm' => $j_m), ' ');
    break;

   case 'g':
    $out .= ($date[0] > 12) ? $date[0] - 12 : $date[0];
    break;

   case 'G':
    $out .= $date[0];
    break;

   case 'h':
    $out .= ($date[0] > 12) ? (($date[0] - 12) < 10 ? '0' . ($date[0] - 12) : $date[0] - 12) : (($date[0] < 10) ? '0' . $date[0] : $date[0]);
    break;

   case 'H':
    $out .= ($date[0] < 10) ? '0' . $date[0] : $date[0];
    break;

   case 'i':
    $out .= ($date[1] < 10) ? '0' . $date[1] : $date[1];
    break;

   case 'j':
    $out .= $j_d;
    break;

   case 'J':
    $out .= jdate_words(array('rr' => $j_d), ' ');
    break;

   case 'l':
    $out .= jdate_words(array('rh' => $date[7]), ' ');
    break;

   case 'L':
    $out .= $kab;
    break;

   case 'm':
    $out .= ($j_m < 10) ? '0' . $j_m : $j_m;
    break;

   case 'M':
    $out .= jdate_words(array('km' => $j_m), ' ');
    break;

   case 'n':
    $out .= $j_m;
    break;

   case 'o':
    $out .= $j_y;
    break;

   case 'O':
    $out .= $date[4];
    break;

   case 'p':
    $out .= jdate_words(array('mb' => $j_m), ' ');
    break;

   case 'P':
    $out .= $date[5];
    break;

   case 'q':
    $out .= jdate_words(array('sh' => $j_y), ' ');
    break;

   case 'Q':
    $out .= $kab + 364 - $doy;
    break;

   case 'r':
    $key = jdate_words(array('rh' => $date[7], 'mm' => $j_m));
    $out .= $date[0] . ':' . $date[1] . ':' . $date[6] . ' ' . $date[4] . ' ' . $key['rh'] . '، ' . $j_d . ' ' . $key['mm'] . ' ' . $j_y;
    break;

   case 's':
    $out .= ($date[6] < 10) ? '0' . $date[6] : $date[6];
    break;

   case 'S':
    $out .= 'ام';
    break;

   case 't':
    $out .= ($j_m != 12) ? (31 - (int)($j_m / 6.5)) : ($kab + 29);
    break;

   case 'U':
    $out .= $ts;
    break;

   case 'v':
    $out .= jdate_words(array('ss' => ($j_y % 100)), ' ');
    break;

   case 'V':
    $out .= jdate_words(array('ss' => $j_y), ' ');
    break;

   case 'w':
    $out .= ($date[7] == 6) ? 0 : $date[7] + 1;
    break;

   case 'W':
    $avs = (($date[7] == 6) ? 0 : $date[7] + 1) - ($doy % 7);
    if ($avs < 0) $avs += 7;
    $num = (int)(($doy + $avs) / 7);
    if ($avs < 4) {
     $num++;
    } elseif ($num < 1) {
     $num = ($avs == 4 or $avs == (((((($j_y % 33) % 4) - 2) == ((int)(($j_y % 33) * 0.05))) ? 5 : 4))) ? 53 : 52;
    }
    $aks = $avs + $kab;
    if ($aks == 7) $aks = 0;
    $out .= (($num < 10) ? '0' . $num : $num) . '،' . $aks;
    break;

   case 'y':
    $out .= substr($j_y, 2, 2);
    break;

   case 'Y':
    $out .= $j_y;
    break;

   case 'z':
    $out .= $doy;
    break;

   default:
    $out .= $sub;
  }
 }
 return ($tr_num != 'en') ? tr_num($out, 'fa', '.') : $out;
}

function jstrftime($format, $timestamp = '', $none = '', $time_zone = 'Asia/Tehran', $tr_num = 'fa')
{

 $T_sec = 0; /* <= رفع خطای زمان سرور ، با اعداد '+' و '-' میتوانید زمان سرور را تنظیم کنید */

 if ($time_zone != 'local') date_default_timezone_set(($time_zone === '') ? 'Asia/Tehran' : $time_zone);
 $ts = $T_sec + (($timestamp === '') ? time() : tr_num($timestamp));
 $date = explode('_', date('h_H_i_j_n_s_w_Y', $ts));
 list($j_y, $j_m, $j_d) = gregorian_to_jalali($date[7], $date[4], $date[3]);
 $sl = strlen($format);
 $out = '';
 for ($i = 0; $i < $sl; $i++) {
  $sub = substr($format, $i, 1);
  if ($sub == '%') {
   $sub = substr($format, ++$i, 1);
  } else {
   $out .= $sub;
   continue;
  }
  switch ($sub) {

    /* Day */
   case 'a':
    $out .= jdate_words(array('kh' => $date[6]), ' ');
    break;

   case 'A':
    $out .= jdate_words(array('rh' => $date[6]), ' ');
    break;

   case 'd':
    $out .= ($j_d < 10) ? '0' . $j_d : $j_d;
    break;

   case 'e':
    $out .= ($j_d < 10) ? ' ' . $j_d : $j_d;
    break;

   case 'j':
    $out .= str_pad(jdate('z', $ts) + 1, 3, 0, STR_PAD_LEFT);
    break;

   case 'u':
    $out .= $date[6] + 1;
    break;

   case 'w':
    $out .= ($date[6] == 6) ? 0 : $date[6] + 1;
    break;

    /* Week */
   case 'U':
    $avs = (($date[6] < 5) ? $date[6] + 2 : $date[6] - 5) - (jdate('z', $ts) % 7);
    if ($avs < 0) $avs += 7;
    $num = (int)((jdate('z', $ts) + $avs) / 7) + 1;
    $out .= ($num < 10) ? '0' . $num : $num;
    break;

   case 'V':
    $out .= jdate('W', $ts, '', '', 'en');
    break;

   case 'W':
    $avs = (($date[6] == 6) ? 0 : $date[6] + 1) - (jdate('z', $ts) % 7);
    if ($avs < 0) $avs += 7;
    $num = (int)((jdate('z', $ts) + $avs) / 7) + 1;
    $out .= ($num < 10) ? '0' . $num : $num;
    break;

    /* Month */
   case 'b':
   case 'h':
    $out .= jdate_words(array('km' => $j_m), ' ');
    break;

   case 'B':
    $out .= jdate_words(array('mm' => $j_m), ' ');
    break;

   case 'm':
    $out .= ($j_m < 10) ? '0' . $j_m : $j_m;
    break;

    /* Year */
   case 'C':
    $out .= (int)($j_y / 100);
    break;

   case 'g':
    $jdw = jdate('w', $ts, '', '', 'en');
    $dny = jdate('z', $ts, '', '', 'en');
    $out .= substr(($j_y + (int)(($dny / 7)) - (int)(($jdw / 7))), 2, 2);
    break;

   case 'G':
    $jdw = jdate('w', $ts, '', '', 'en');
    $dny = jdate('z', $ts, '', '', 'en');
    $out .= $j_y + (int)(($dny / 7)) - (int)(($jdw / 7));
    break;

   case 'y':
    $out .= substr($j_y, 2, 2);
    break;

   case 'Y':
    $out .= $j_y;
    break;

    /* Time */
   case 'H':
    $out .= $date[1];
    break;

   case 'I':
    $out .= $date[0];
    break;

   case 'l':
    $out .= ($date[0] > 12) ? $date[0] - 12 : $date[0];
    break;

   case 'M':
    $out .= $date[2];
    break;

   case 'p':
    $out .= ($date[1] < 12) ? 'قبل از ظهر' : 'بعد از ظهر';
    break;

   case 'P':
    $out .= ($date[1] < 12) ? 'ق.ظ' : 'ب.ظ';
    break;

   case 'r':
    $out .= $date[0] . ':' . $date[2] . ':' . $date[5] . ' ' . (($date[1] < 12) ? 'قبل از ظهر' : 'بعد از ظهر');
    break;

   case 'R':
    $out .= $date[1] . ':' . $date[2];
    break;

   case 'S':
    $out .= $date[5];
    break;

   case 'T':
    $out .= $date[1] . ':' . $date[2] . ':' . $date[5];
    break;

   case 'X':
    $out .= $date[0] . ':' . $date[2] . ':' . $date[5];
    break;

   case 'z':
    $out .= date('O', $ts);
    break;

   case 'Z':
    $out .= date('T', $ts);
    break;

    /* Time and Date Stamps */
   case 'c':
    $key = jdate_words(array('rh' => $date[6], 'mm' => $j_m));
    $out .= $date[1] . ':' . $date[2] . ':' . $date[5] . ' ' . date('P', $ts) . ' ' . $key['rh'] . '، ' . $j_d . ' ' . $key['mm'] . ' ' . $j_y;
    break;

   case 'D':
    $out .= substr($j_y, 2, 2) . '/' . (($j_m < 10) ? '0' . $j_m : $j_m) . '/' . (($j_d < 10) ? '0' . $j_d : $j_d);
    break;

   case 'F':
    $out .= $j_y . '-' . (($j_m < 10) ? '0' . $j_m : $j_m) . '-' . (($j_d < 10) ? '0' . $j_d : $j_d);
    break;

   case 's':
    $out .= $ts;
    break;

   case 'x':
    $out .= substr($j_y, 2, 2) . '/' . (($j_m < 10) ? '0' . $j_m : $j_m) . '/' . (($j_d < 10) ? '0' . $j_d : $j_d);
    break;

    /* Miscellaneous */
   case 'n':
    $out .= "\n";
    break;

   case 't':
    $out .= "\t";
    break;

   case '%':
    $out .= '%';
    break;

   default:
    $out .= $sub;
  }
 }
 return ($tr_num != 'en') ? tr_num($out, 'fa', '.') : $out;
}

function jmktime($h = '', $m = '', $s = '', $jm = '', $jd = '', $jy = '', $none = '', $timezone = 'Asia/Tehran')
{
 if ($timezone != 'local') date_default_timezone_set($timezone);
 if ($h === '') {
  return time();
 } else {
  list($h, $m, $s, $jm, $jd, $jy) = explode('_', tr_num($h . '_' . $m . '_' . $s . '_' . $jm . '_' . $jd . '_' . $jy));
  if ($m === '') {
   return mktime($h);
  } else {
   if ($s === '') {
    return mktime($h, $m);
   } else {
    if ($jm === '') {
     return mktime($h, $m, $s);
    } else {
     $j_y = $jy;
     $j_m = $jm;
     $j_d = $jd;
     list($g_y, $g_m, $g_d) = jalali_to_gregorian($j_y, $j_m, $j_d);
     return mktime($h, $m, $s, $g_m, $g_d, $g_y);
    }
   }
  }
 }
}

function jgetdate($timestamp = '', $none = '', $timezone = 'Asia/Tehran', $tn = 'en')
{
 $ts = ($timestamp === '') ? time() : tr_num($timestamp);
 $jdate = explode('_', jdate('F_G_i_j_l_m_s_w_Y_z', $ts, '', $timezone, $tn));
 return array(
  'seconds' => tr_num((int)tr_num($jdate[6]), $tn),
  'minutes' => tr_num((int)tr_num($jdate[2]), $tn),
  'hours' => $jdate[1],
  'mday' => $jdate[3],
  'wday' => $jdate[7],
  'mon' => $jdate[5],
  'year' => $jdate[8],
  'yday' => $jdate[9],
  'weekday' => $jdate[4],
  'month' => $jdate[0],
  0 => tr_num($ts, $tn)
 );
}

function jcheckdate($j_m, $j_d, $j_y)
{
 list($j_m, $j_d, $j_y) = explode('_', tr_num($j_m . '_' . $j_d . '_' . $j_y));
 $lm = ($j_m == 12) ? (jdate('L', jmktime(0, 0, 0, 1, 1, $j_y)) + 29) : (31 - (int)($j_m / 6.5));
 return ($j_m > 0 and $j_d > 0 and $j_y > 0 and $j_m < 13 and $j_d <= $lm) ? true : false;
}

function tr_num($str, $mod = 'en', $mf = '٫')
{
 $num_a = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.');
 $key_a = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', $mf);
 return ($mod == 'fa') ? str_replace($num_a, $key_a, $str) : str_replace($key_a, $num_a, $str);
}

function jdate_words($array, $mod = '')
{
 foreach ($array as $type => $num) {
  $num = (int)tr_num($num);
  switch ($type) {

   case 'ss':
    $sl = strlen($num);
    $xy3 = substr($num, 2 - $sl, 1);
    $h3 = $h34 = $h4 = '';
    if ($xy3 == 1) {
     $p34 = '';
     $k34 = array('ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده');
     $h34 = $k34[substr($num, 2 - $sl, 2) - 10];
    } else {
     $xy4 = substr($num, 3 - $sl, 1);
     $p34 = ($xy3 == 0 or $xy4 == 0) ? '' : ' و ';
     $k3 = array('', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود');
     $h3 = $k3[$xy3];
     $k4 = array('', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه');
     $h4 = $k4[$xy4];
    }
    $out[$type] = (($num > 99) ? jdate_words(array('ss' => (int)($num / 100))) . ' صد و ' : '') . $h3 . $p34 . $h34 . $h4;
    break;

   case 'rr':
    $key = array('یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه', 'ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده', 'بیست', 'بیست و یک', 'بیست و دو', 'بیست و سه', 'بیست و چهار', 'بیست و پنج', 'بیست و شش', 'بیست و هفت', 'بیست و هشت', 'بیست و نه', 'سی', 'سی و یک');
    $out[$type] = $key[$num - 1];
    break;

   case 'rh':
    $key = array('یکشنبه', 'دوشنبه', 'سه شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه');
    $out[$type] = $key[$num];
    break;

   case 'sh':
    $key = array('مار', 'اسب', 'گوسفند', 'میمون', 'مرغ', 'سگ', 'خوک', 'موش', 'گاو', 'پلنگ', 'خرگوش', 'نهنگ');
    $out[$type] = $key[$num % 12];
    break;

   case 'mb':
    $key = array('حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله', 'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت');
    $out[$type] = $key[$num - 1];
    break;

   case 'ff':
    $key = array('بهار', 'تابستان', 'پاییز', 'زمستان');
    $out[$type] = $key[(int)($num / 3.1)];
    break;

   case 'km':
    $key = array('فر', 'ار', 'خر', 'تی‍', 'مر', 'شه‍', 'مه‍', 'آب‍', 'آذ', 'دی', 'به‍', 'اس‍');
    $out[$type] = $key[$num - 1];
    break;

   case 'kh':
    $key = array('ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش');
    $out[$type] = $key[$num];
    break;

   default:
    $key = array('فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند');
    $out[$type] = $key[$num - 1];
  }
 }
 return ($mod === '') ? $out : implode($mod, $out);
}