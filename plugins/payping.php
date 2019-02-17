<?php
	$pluginData['payping']['type'] = 'payment';
	$pluginData['payping']['name'] = 'دروازه پرداخت پی‌پینگ';
	$pluginData['payping']['uniq'] = 'payping';
	$pluginData['payping']['description'] = 'جهت پرداخت از سامانه <a target="_blank" href="https://www.payping.ir/">پی‌پینگ</a>';
	$pluginData['payping']['author']['name'] = 'Erfan Ebrahimi';
	$pluginData['payping']['author']['url'] = 'http://erfanebrahimi.ir';
	$pluginData['payping']['author']['email'] = 'me@erfanebrahimi.ir';
	$pluginData['payping']['field']['config'][1]['title'] = 'توکن پی پینگ';
	$pluginData['payping']['field']['config'][1]['name'] = 'token';
	$pluginData['payping']['field']['config'][2]['title'] = 'عنوان خرید برای توضیحات درگاه پرداخت';
	$pluginData['payping']['field']['config'][2]['name'] = 'title';

	function gateway__payping($data)
	{
		global $config,$db,$smarty;
		if ( $data['currency'] != 1 or $data['currency'] != 10 )
			$data['currency'] = 10 ;

		$token 	= trim($data['token']);
		$amount 		= round($data['amount']/10);

// 		$data_send = array( 'Amount' => $amount,'payerIdentity'=> $data['mobile'] , 'returnUrl' => $data['callback'], 'Description' => $data['title'].' - '.$data['invoice_id'] , 'clientRefId' => $data['invoice_id']  );
		$data_send = array( 'Amount' => $amount, 'returnUrl' => $data['callback'], 'Description' => $data['title'].' - '.$data['invoice_id'] , 'clientRefId' => $data['invoice_id']  );
		try {
			$curl = curl_init();
			curl_setopt_array($curl, array(CURLOPT_URL => "https://api.payping.ir/v1/pay", CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 30, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => json_encode($data_send), CURLOPT_HTTPHEADER => array("accept: application/json", "authorization: Bearer " . $token , "cache-control: no-cache", "content-type: application/json"),));
			$response = curl_exec($curl);
			$header = curl_getinfo($curl);
			$err = curl_error($curl);
			curl_close($curl);
			if ($err) {
				echo "cURL Error #:" . $err;
			} else {
				if ($header['http_code'] == 200) {
					$response = json_decode($response, true);
					if (isset($response["code"]) and $response["code"] != '') {
						header('location:'.sprintf('https://api.payping.ir/v1/pay/gotoipg/%s', $response["code"]));
						exit;
					} else {
						$Message = ' تراکنش ناموفق بود- شرح خطا : عدم وجود کد ارجاع ';
					}
				} elseif ($header['http_code'] == 400) {
					$Message = ' تراکنش ناموفق بود- شرح خطا : ' . implode('. ',array_values (json_decode($response,true))) ;
				} else {
					$Message = ' تراکنش ناموفق بود- شرح خطا : ' . status_message_payPing($header['http_code']) . '(' . $header['http_code'] . ')';
				}
			}
		} catch (Exception $e){
			$Message = ' تراکنش ناموفق بود- شرح خطا سمت برنامه شما : ' . $e->getMessage();
		}

		if ( $Message != '' ) {
			$data['title'] = 'خطای سیستم';
			$data['message'] = '<font color="red">'.$Message.'</font><br /><a href="index.php" class="button">بازگشت</a>';
			$query	= 'SELECT * FROM `config` WHERE `config_id` = "1" LIMIT 1';
			$conf	= $db->fetch($query);
			$smarty->assign('config', $conf);
			$smarty->assign('data', $data);
			$smarty->display('message.tpl');
		}
		return ;
	}

	function callback__payping($data)
	{
		global $db,$get;


		$sql 		= 'SELECT * FROM `payment` WHERE `payment_rand` = "'.$_GET['clientrefid'].'" LIMIT 1;';
		$payment 	= $db->fetch($sql);

		$data_send = array('refId' => $_GET['refid'], 'amount' => round($payment['payment_amount']/10));
		try {
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => "https://api.payping.ir/v1/pay/verify",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_POSTFIELDS => json_encode($data_send),
				CURLOPT_HTTPHEADER => array(
					"accept: application/json",
					"authorization: Bearer ".trim($data['token']),
					"cache-control: no-cache",
					"content-type: application/json",
				),
			));
			$response = curl_exec($curl);
			$err = curl_error($curl);
			$header = curl_getinfo($curl);
			curl_close($curl);
			if ($err) {
				$output['status']	= 0;
				$output['ref_num']	= $_GET["refid"];
				$output['message'] = 'خطا در ارتباط به پی‌پینگ : شرح خطا '.$err;
			} else {
				if ($header['http_code'] == 200) {
					$response = json_decode($response, true);
					if (isset($_GET["refid"]) and $_GET["refid"] != '') {
						$output['status']		= 1;
						$output['res_num']	= $_GET["refid"];
						$output['ref_num']	= $_GET["refid"];
						$output['payment_id']	= $payment['payment_id'];
					} else {
						$output['status']	= 0;
						$output['ref_num']	= $_GET["refid"];
						$output['message'] = 'متافسانه سامانه قادر به دریافت کد پیگیری نمی باشد! نتیجه درخواست : ' . status_message_payPing($header['http_code']) . '(' . $header['http_code'] . ')' ;

					}
				} elseif ($header['http_code'] == 400) {
					$output['status']	= 0;
					$output['ref_num']	= $_GET["refid"];
					$output['message'] = 'تراکنش ناموفق بود- شرح خطا : ' .  implode('. ',array_values (json_decode($response,true))) ;
				}  else {
					$output['status']	= 0;
					$output['ref_num']	= $_GET["refid"];
					$output['message'] = ' تراکنش ناموفق بود- شرح خطا : ' . status_message_payPing($header['http_code']) . '(' . $header['http_code'] . ')';
				}
			}
		} catch (Exception $e){
			$output['message'] = ' تراکنش ناموفق بود- شرح خطا سمت برنامه شما : ' . $e->getMessage();
			$output['status']	= 0;
			$output['ref_num']	= $_GET["refid"];
		}
		return $output;
	}


function status_message_payPing($code) {
	switch ($code){
		case 200 :
			return 'عملیات با موفقیت انجام شد';
			break ;
		case 400 :
			return 'مشکلی در ارسال درخواست وجود دارد';
			break ;
		case 500 :
			return 'مشکلی در سرور رخ داده است';
			break;
		case 503 :
			return 'سرور در حال حاضر قادر به پاسخگویی نمی‌باشد';
			break;
		case 401 :
			return 'عدم دسترسی';
			break;
		case 403 :
			return 'دسترسی غیر مجاز';
			break;
		case 404 :
			return 'آیتم درخواستی مورد نظر موجود نمی‌باشد';
			break;
	}
}
