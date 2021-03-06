<?php

class ReportController extends BaseController {


	public function getVoucher()
	{
		$v_id = Input::get("voucher_id");
		$vou =  DB::select(DB::raw("select date, narration, name as location, details as location_details FROM vouchers JOIN locations on locations.id=location_id where vouchers.id=".$v_id.";"));

		$gen =  DB::select(DB::raw("select name,  remark, voucher_id as Ref, SUM(dr) as Debit, SUM(cr) as Credit from general_accounts join accounts on accounts.id=account_id where voucher_id=2 GROUP BY (".$v_id.");"));
		
		$a = array("voucher" => $vou, "accounts" => $gen);
		return json_encode($a);	
		
	}
	public function getLedger(){
		$account_id 	= Input::get("account_id");
		$start_date 	= Input::get("start_date");
		$end_date	= Input::get("end_date");
		return json_encode(DB::select(DB::raw("SELECT general_accounts.`voucher_id`, `date`, `account_id`, `against_account_id`, `dr`, `cr`, `balance`, `remark` FROM `general_accounts` JOIN (SELECT `id`, `date` FROM `vouchers` WHERE `date` between '".$start_date." 00:00:00' and '".$end_date." 23:59:00') x ON(general_accounts.voucher_id=x.id) WHERE account_id=".$account_id." ORDER BY `voucher_id`,`date`;")));
	}
	public function getLedgerWithChildsEntry(){

		$account_id 	= Input::get("account_id");
		$start_date 	= Input::get("start_date");
		$end_date	= Input::get("end_date");

		$childList = Utilities::getChildList($account_id);

		$conditions = "account_id=".$account_id;
		foreach ($childList as $child) {
			$conditions = $conditions." OR account_id=".$child;
		}


		return json_encode(DB::select(DB::raw("SELECT general_accounts.`voucher_id`, `date`, `account_id`, `against_account_id`, `dr`, `cr`, `balance`, `remark` FROM `general_accounts` JOIN (SELECT `id`, `date` FROM `vouchers` WHERE `date` between '".$start_date." 00:00:00' and '".$end_date." 23:59:00') x ON(general_accounts.voucher_id=x.id) WHERE ".$conditions." ORDER BY `voucher_id`,`date`;")));
	}
	public function getPartyWiseDetail(){
		$id = Input::get("party_id");
		$obj = new stdClass();
		$obj->partyDetails  =  json_decode(PartyController::getPartyDetails($id));
		if(count($obj->partyDetails)>0){
			 $account_id 	= $obj->partyDetails[0]->account_id;
			$start_date 	= Input::get("start_date");
			$end_date		= Input::get("end_date");
			return json_encode(DB::select(DB::raw("SELECT general_accounts.`voucher_id`, `date`, `account_id`, `against_account_id`, `dr`, `cr`, `balance`, `remark` FROM `general_accounts` JOIN (SELECT `id`, `date` FROM `vouchers` WHERE `date` between '".$start_date." 00:00:00' and '".$end_date." 23:59:00') x ON(general_accounts.voucher_id=x.id) WHERE account_id=".$account_id." ORDER BY `date`;")));

		}else{
			return "No data";
		}

	}

	public function getBalanceSheet(){

		$date = null;
		if(Input::get("date")!=null){
			$date = Input::get("date");
		}else
		 {
		 	$date = '\'00-00-00 00:00:00\'';
		 }
		 $plc = Input::get("plc");
		 if($plc!=null)
		 	return $this->getBalanceSheetOfCnfProjectLc($date, $plc);

		$array = array();
		for($i=7; $i<=57; $i++){
			$Object = new stdClass();
			$Object->id = $i;
			$childs = Utilities::getChildList($i);
			$balance = Utilities::getCurrentBalance($i, $date);
			foreach ($childs as $child) {
				$balance = $balance + Utilities::getCurrentBalance($child, $date);
			}
			$Object->totalBalance = $balance;
			$array[] = $Object;
		}
		return json_encode($array);
	}
	
	public function getBalanceSheetOfCnfProjectLc($date, $plc){
		$array = array();
		for($i=7; $i<=57; $i++){
			$Object = new stdClass();
			$Object->id = $i;
			$childs = Utilities::getChildList($i);
			$balance = Utilities::getCurrentBalanceForPLC($i, $plc,  $date);
			foreach ($childs as $child) {
				$balance = $balance + Utilities::getCurrentBalanceForPLC($child, $plc, $date);
			}
			$Object->totalBalance = $balance;
			$array[] = $Object;
		}
		return json_encode($array);
	}
	public function getPartyReport(){
		$startDate = Input::get("start_date");
		$endDate  = Input::get("end_date");
		if($startDate==null||$endDate==null)
			return json_encode(array("Status"=>"Failed", "Message"=>"No date Found"));
		
		$query =	"SELECT  first_t.name as party_name, first_t.balance as opening_balance, second_t.dr as dr, 
				second_t.cr as cr, first_t.balance+second_t.dr-second_t.cr as balance FROM
				(
					SELECT  y.id, y.name, y.balance, y.account_id FROM
					(	
		    			SELECT x.id, x.name, x.voucher_id, x.account_id, x.balance, vouchers.date FROM
						(	
		           			 SELECT general_accounts.`id`, parties.name, `voucher_id`, 	
		           			 general_accounts.`account_id`,`balance` FROM `general_accounts` 	
		            		JOIN parties ON parties.account_id = general_accounts.account_id
		        		) x 
		    			INNER JOIN vouchers ON vouchers.id=x.voucher_id AND vouchers.date >= '".$startDate."'
		    			 AND vouchers.date <= '".$endDate."'
					)  y INNER JOIN 

					(
		  				 SELECT  MIN(p.id) as id FROM
		  				 (
		    				SELECT x.id,  x.voucher_id, x.account_id, x.balance FROM
							(	
		           				SELECT general_accounts.`id`, parties.name, `voucher_id`, 	
		            			general_accounts.`account_id`,`balance` FROM `general_accounts` 	
		            			JOIN parties ON parties.account_id = general_accounts.account_id
		        			) x 
		   					 INNER JOIN vouchers ON vouchers.id=x.voucher_id AND vouchers.date >= '".$startDate."'
		   					 AND vouchers.date <= '".$endDate."'
		    			) p
		   				 GROUP BY p.account_id
		    
					) z ON y.id=z.id
				) first_t INNER JOIN 
				(
					SELECT   tt.account_id, SUM(tt.cr) as cr, SUM(tt.dr) as dr FROM
					(
		   				 SELECT x.*, vouchers.date FROM
						(
		        			SELECT general_accounts.`id`, parties.name, `voucher_id`, 
		        			general_accounts.`account_id`, 		`dr`, `cr`, `balance` FROM 
		        			`general_accounts` JOIN parties ON parties.account_id		
		       				 =general_accounts.account_id
		   				 ) x 
		   				 INNER JOIN vouchers ON vouchers.id=x.voucher_id AND 
		   				 vouchers.date >= '".$startDate."' AND vouchers.date <= '".$endDate."'
					) tt GROUP BY tt.account_id
				) second_t ON first_t.account_id=second_t.account_id;";
		return json_encode(DB::select(DB::raw($query)));

	}
	/*****************OLD With 1 level child of first account****************
	/*public function getBalanceSheetOfCnfProjectLc($date, $plc){
		$array = array();
		for($i=7; $i<=57; $i++){
			$Object = new stdClass();
			$Object->id = $i;
			$Object->balance = Utilities::getCurrentBalanceForPLC($i, $plc,  $date);
			$fChilds = Utilities::getChild_level1($i);
			$Object->children = array();
			foreach ($fChilds as  $fChild) {
				$ch = new stdClass();
				$ch->id = $fChild;
				$ch->balance = Utilities::getCurrentBalanceForPLC($fChild, $plc,  $date);
				$childs = Utilities::getChildList($fChild);
				foreach ($childs as $child) {
					$ch->balance = $ch->balance + Utilities::getCurrentBalanceForPLC($child, $plc, $date);
				}
				$Object->children[] = $ch;
			}
			$array[] = $Object;
		}
		return json_encode($array);
	}*/
	

	public function getTrialBalance(){
		$balance = array();
		$accounts = DB::table('accounts')->get();
		foreach ($accounts as $account) {
			$id = $account->id;
			$general_acc = DB::select(DB::raw('SELECT balance FROM `general_accounts` WHERE account_id='. $id .' order by id desc limit 1'));
			array_push($balance, array(
					'id'		=>	$account->id,
					'name'		=>	$account->name,
					'balance'	=>	$general_acc[0]->balance
				));
			
		}


		return json_encode($balance);
	}
	public function getTrialBalWithDateInternal($needCondition, $beforeCondition, $id, $space, $flag){


		$general_acc_need = DB::select(DB::raw('SELECT balance FROM `general_accounts` WHERE account_id='. $id."".$needCondition.' order by id desc limit 1'));
		$general_acc_before =  DB::select(DB::raw('SELECT balance FROM `general_accounts` WHERE account_id='. $id."".$beforeCondition.' order by id desc limit 1'));
	
		$account =  DB::select(DB::raw('SELECT `id`, `name` FROM `accounts` WHERE `id` = '.$id." limit 1;"));
		
		$needBal = 0.0;

		if($needCondition!=""&&sizeof($general_acc_need)>0){
			$needBal = $general_acc_need[0]->balance;
		}
		$beforeBal = 0.0;

		if($beforeCondition!=""&&sizeof($general_acc_before)>0){
			$beforeBal = $general_acc_before[0]->balance;
		}

		$result = array();

		if($id!=1){
			array_push($result, array(
				'id'		=>	$account[0]->id,
				'name'		=>	$space.$account[0]->name,
				'balance'	=>	$needBal-$beforeBal
			));
		} 
		
		$childs = Utilities::getChild_level1($account[0]->id);
		foreach($childs as $chld){
			if($id!=1&&$flag==1)
				$temp = $this->getTrialBalWithDateInternal($needCondition, $beforeCondition, $chld, $space."  ", $flag);
			else
				$temp = $this->getTrialBalWithDateInternal($needCondition, $beforeCondition, $chld, $space, $flag);
			$result = array_merge($result, $temp);
		}



		return $result;
	}

	public function getTrialBalanceWithDate(){
		$startDate = Input::get("start_date");
		$endDate  = Input::get("end_date");
		return $this->getAccountsBalanceWithDate($startDate, $endDate, 1, 1);

	}

	public function getAccountsBalanceWithDate($startDate, $endDate, $acc_id, $flag_space){

		$neededVoucherid = DB::select(DB::raw("SELECT `id` FROM `vouchers` WHERE `date` < '".$endDate." 23:59:59';"));
		$needCondition = "";

		$first = 1;
		foreach($neededVoucherid as $need){
			if($first!=1)
				$needCondition = $needCondition." OR voucher_id=".$need->id;
			else
				$needCondition = $needCondition." voucher_id=".$need->id;
			$first = 2;
		}

		if($needCondition!=""){
			$needCondition = " AND (".$needCondition." )";
		}
		$beforeVoucherId = DB::select(DB::raw("SELECT `id` FROM `vouchers` WHERE `date` < '".$startDate." 00:00:00';"));
		
		$first = 1;
		$beforeCondition = "";
		foreach($beforeVoucherId as $before){
			if($first!=1)
				$beforeCondition = $beforeCondition." OR voucher_id=".$before->id;
			else
				$beforeCondition = $beforeCondition." voucher_id=".$before->id;
			$first = 2;
		}


		if($beforeCondition!=""){
			$beforeCondition = " AND ( ".$beforeCondition." )";
		}


		$result = $this->getTrialBalWithDateInternal($needCondition, $beforeCondition, $acc_id, "", $flag_space);

		return json_encode($result);
	
	}
	public function getChildCondition($acc_id){
		$allChilds = Utilities::getChildList($acc_id);
		$childCondition = " (`account_id`='$acc_id' ";

		foreach ($allChilds as $child) {
				$childCondition = " $childCondition OR `account_id`='$child' ";
		}
		$childCondition = $childCondition.")"; 
		return $childCondition;

	}
	public function getVoucherIdCondition($startDate, $endDate){
			$ids = DB::select(DB::raw("SELECT `id` FROM `vouchers` WHERE `date`>'".$startDate." 00:00:00' AND `date`< '".$endDate." 23:59:59';"));
			$voucherIdConditon = " (`voucher_id` = '' ";
			foreach ($ids as $id) {
				$voucherIdConditon = " $voucherIdConditon OR `voucher_id` = '$id->id' ";
			}
			$voucherIdConditon = $voucherIdConditon.")";
			return $voucherIdConditon;

	}
	public function getVoucherIdByOneDate($date, $isBeg){
			$ids;
			if($isBeg==1){
				$ids = DB::select(DB::raw("SELECT `id` FROM `vouchers` WHERE `date`<'".$date."00:00:00';"));
			}else{
				$ids = DB::select(DB::raw("SELECT `id` FROM `vouchers` WHERE `date`<'".$date."23:59:59';"));

			}
			$voucherIdConditon = " (`voucher_id` = '' ";
			foreach ($ids as $id) {
				$voucherIdConditon = " $voucherIdConditon OR `voucher_id` = '$id->id' ";
			}
			$voucherIdConditon = $voucherIdConditon.")";
			return $voucherIdConditon;

	}

	public function getFinancialStatementByDate(){ 
			$startDate = Input::get('start_date');
			$endDate = Input::get('end_date');

			$object = new stdClass();
			$childCondition = $this->getChildCondition(21);
			$voucherIdConditon = $this->getVoucherIdCondition($startDate, $endDate);
			$sqlForTradeDebtors = "SELECT SUM(`cr`) as receipts_from_trade_debtors FROM `general_accounts` WHERE $childCondition AND $voucherIdConditon;";
			$receptTradeDators = DB::select(DB::raw($sqlForTradeDebtors));
			$object->receipts_from_trade_debtors = $receptTradeDators[0]->receipts_from_trade_debtors ==null? '0.00': $receptTradeDators[0]->receipts_from_trade_debtors;

			//Non operating Income acc_id = 38
			$allChildsNonOp = $this->getChildCondition(38);
			$sqlForOpIncome = "SELECT SUM(`cr`) as other_income FROM `general_accounts` WHERE $allChildsNonOp AND $voucherIdConditon;";
			$other_income = DB::select(DB::raw($sqlForOpIncome));
			$object->other_income = $other_income[0]->other_income ==null? '0.00': $other_income[0]->other_income;

			$object->payments = json_decode($this->getAccountsBalanceWithDate($startDate, $endDate, 6, 0));

			//Cash in Hand
			$cashInHandBeg = DB::select(DB::raw("SELECT balance FROM `general_accounts` WHERE ".$this->getChildCondition(23)." AND ".$this->getVoucherIdByOneDate($startDate, 1)." order by id desc limit 1"));

			if(sizeof($cashInHandBeg)<=0)
				$object->cash_in_hand_opening = "0.0";
			else
				$object->cash_in_hand_opening = $cashInHandBeg[0]->balance;
			$cashInHandEnd = DB::select(DB::raw("SELECT balance FROM `general_accounts` WHERE ".$this->getChildCondition(23)." AND ".$this->getVoucherIdByOneDate($endDate, 0)." order by id desc limit 1"));
			
			if(sizeof($cashInHandEnd)<=0)
				$object->cash_in_hand_closing = "0.0";
			else
				$object->cash_in_hand_closing = $cashInHandEnd[0]->balance;

			//Cash At Bank
			$cashAtBankBeg = DB::select(DB::raw("SELECT balance FROM `general_accounts` WHERE ".$this->getChildCondition(22)." AND ".$this->getVoucherIdByOneDate($startDate, 1)." order by id desc limit 1"));
			if(sizeof($cashAtBankBeg)<=0)
				$object->cash_at_bank_opening = "0.0";
			else
				$object->cash_at_bank_opening = $cashAtBankBeg[0]->balance;

			$cashAtBankEnd = DB::select(DB::raw("SELECT balance FROM `general_accounts` WHERE ".$this->getChildCondition(22)." AND ".$this->getVoucherIdByOneDate($endDate, 0)." order by id desc limit 1"));
			
			if(sizeof($cashAtBankEnd)<=0)
				$object->cash_at_bank_closing = "0.0";
			else
				$object->cash_at_bank_closing = $cashAtBankEnd[0]->balance;
		

			return json_encode($object);
	}


}
