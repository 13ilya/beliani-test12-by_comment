<?php

// require_once 'Geschaeftskundenversand/GeschaeftskundenversandWS/ISService_1_0_de.php';

class GeschaeftskundenversandRequestBuilder {
	
	private $SHIPPER_STREET = "Heinrich-Bruening-Str.";
	private $SHIPPER_STREETNR = "7";
	private $SHIPPER_CITY = "Bonn";
	private $SHIPPER_ZIP = "53113";
	private $SHIPPER_COUNTRY_CODE = "DE";
	private $SHIPPER_CONTACT_EMAIL = "max@muster.de";
	private $SHIPPER_CONTACT_NAME = "Max Muster";
	private $SHIPPER_CONTACT_PHONE = "030244547777778";
	private $SHIPPER_COMPANY_NAME = "Deutsche Post IT Brief GmbH";
	//	private $ENCODING = "UTF8";
	private $MAJOR_RELEASE = "1";
	private $MINOR_RELEASE = "0";
	private $SDF = 'Y-m-d';
	private $DD_PROD_CODE = "EPN";
	private $TD_PROD_CODE = "WPX";
	private $TEST_EKP = "5000000008";
	private $PARTNER_ID = "01";
	private $SHIPMENT_DESC = "Interessanter Artikel";
	private $TD_SHIPMENT_REF = "DDU";
	private $TD_VALUE_GOODS = 250;
	private $TD_CURRENCY = "EUR";
	private $TD_ACC_NUMBER_EXPRESS = "144405785";
	private $TD_DUTIABLE = "1";
	
	
	//Beispieldaten Für DD-Sendungen aus/nach Deutschland
	private $RECEIVER_FIRST_NAME = "Kai";
	private $RECEIVER_LAST_NAME = "Wahn";
	private $RECEIVER_LOCAL_STREET = "Marktplatz";
	private $RECEIVER_LOCAL_STREETNR = "1";
	private $RECEIVER_LOCAL_CITY = "Stuttgart";
	private $RECEIVER_LOCAL_ZIP = "70173";
	private $RECEIVER_LOCAL_COUNTRY_CODE = "DE";
	
	//Beispieldaten Für TD-Sendungen weltweit
	private $RECEIVER_WWIDE_STREET = "Chung Hsiao East Road.";
	private $RECEIVER_WWIDE_STREETNR = "55";
	private $RECEIVER_WWIDE_CITY = "Taipeh";
	private $RECEIVER_WWIDE_ZIP = "100";
	private $RECEIVER_WWIDE_COUNTRY = "Taiwan";
	private $RECEIVER_WWIDE_COUNTRY_CODE = "TW";
	
	private $RECEIVER_CONTACT_EMAIL = "kai@wahn.de";
	private $RECEIVER_CONTACT_NAME = "Kai Wahn";
	private $RECEIVER_CONTACT_PHONE = "+886 2 27781-8";
	private $RECEIVER_COMPANY_NAME = "Klammer Company";
	private $DUMMY_SHIPMENT_NUMBER = "0000000";
	
	private $EXPORT_REASON = "Sale";
	private $SIGNER_TITLE = "Director Asia Sales";
	private $EXPORT_TYPE = "P";
	private $INVOICE_NUMBER = "200601xx417";
	private $INVOICE_TYPE = "commercial";
	private $DUMMY_AIRWAY_BILL = "0000000000";
	
	
	public function createVersion() {
		$build = null;
		$version = new Version($this->MAJOR_RELEASE, $this->MINOR_RELEASE, $build);
		return $version;
	}
	
	
	public function createDefaultShipmentItemDDType() {
		$HeightInCM = "15";
		$LengthInCM = "30";
		$WeightInKG = "3";
		$WidthInCM = "30";
		$shipmentItem = new ShipmentItemType($WeightInKG, $LengthInCM, $WidthInCM, $HeightInCM);
		$shipmentItem->PackageType = "PK";
		return $shipmentItem;
	}	
	
	
	//Als Beispiel: shipmentDetails mit 1 shipmentItem 
	public function createShipmentDetailsDDType() {
		$today = new DateTime();
		$today->add(new DateInterval('P2D'));		
	
		$EKP = $this->TEST_EKP;
		$partnerID = $this->PARTNER_ID;
		$Attendance = new Attendance($partnerID);
		
		$CustomerReference = null;
		$Description = $this->SHIPMENT_DESC;
		$DeliveryRemarks = null;
		$Service = null;
		$Notification = null;
		$BankData = null;
		$ShipmentItem = $this->createDefaultShipmentItemDDType();
	
		$shipmentDetails = new ShipmentDetailsDDType($EKP, $Attendance, $CustomerReference, $Description, $DeliveryRemarks, $ShipmentItem, $Service, $Notification, $BankData);
		$shipmentDetails->ProductCode = $this->DD_PROD_CODE;
		$shipmentDetails->ShipmentDate = $today->format($this->SDF);
		
		return $shipmentDetails;
	}
	
	
	public function createShipperCompany() {
		$company = new Company($this->SHIPPER_COMPANY_NAME, null);
		$name = new NameType(null, $company);
		return $name;
	}
	
	
	public function createShipperNativeAddressType() {	
		$streetName = $this->SHIPPER_STREET;
		$streetNumber = $this->SHIPPER_STREETNR;
		$city = $this->SHIPPER_CITY;
		
		$germany = $this->SHIPPER_ZIP;
		$england = null;
		$other = null;
		$zip = new ZipType($germany, $england, $other);
		
		$country = null;
		$countryISOCode = $this->SHIPPER_COUNTRY_CODE;
		$state = null;
		$Origin = new CountryType($country, $countryISOCode, $state);

		$floorNumber = null;
		$roomNumber = null;
		$languageCodeISO = null;
		$note = null;
		$careOfName=null;
		$district= null;
		
		$address = new NativeAddressType($streetName, $streetNumber, $careOfName, $zip, $city, $district, $Origin, $floorNumber, $roomNumber, $languageCodeISO, $note);
		return $address;
	}
	
	
	public function createShipperCommunicationType() {
		$phone = $this->SHIPPER_CONTACT_PHONE;
		$email = $this->SHIPPER_CONTACT_EMAIL;
		$fax = null; 
		$mobile = null;
		$internet = null;
		$contactPerson = $this->SHIPPER_CONTACT_NAME;
		$communication = new CommunicationType($phone, $email, $fax, $mobile, $internet, $contactPerson);
		
		return $communication;
	}
	
	
	public function createReceiverCompany($isPerson) {
		$Person = null;
		$Company = null;
		if ($isPerson){
			$firstname = $this->RECEIVER_FIRST_NAME;
			$lastname = $this->RECEIVER_LAST_NAME;			
			$salutation= null;
			$title= null;
			$middlename= null;
			$Person = new Person($salutation, $title, $firstname, $middlename, $lastname);
		} else{
			$name1 = $this->RECEIVER_COMPANY_NAME;
			$name2 = null;
			$Company = new Company($name1, name2);
		}
		$name = new NameType($Person, $Company);
		return $name;
	}
	
	
	public function createReceiverNativeAddressType($worldwide) {
	
		$streetName=null;
		$streetNumber=null;
		$city=null;
		$germany=null;
		$england=null;
		$other=null;
		$countryISOCode=null;
		$country=null;
		$state=null;
		$careOfName=null;
		$district=null;
		$floorNumber=null;
		$roomNumber=null;
		$languageCodeISO=null;
		$note=null;
		
		if (!$worldwide){
			$streetName = $this->RECEIVER_LOCAL_STREET;
			$streetNumber = $this->RECEIVER_LOCAL_STREETNR;
			$city = $this->RECEIVER_LOCAL_CITY;
			$germany = $this->RECEIVER_LOCAL_ZIP;
			$countryISOCode = $this->RECEIVER_LOCAL_COUNTRY_CODE;
		} else {
			$streetName = $this->RECEIVER_WWIDE_STREET;
			$streetNumber = $this->RECEIVER_WWIDE_STREETNR;
			$city = $this->RECEIVER_WWIDE_CITY;
			$other = $this->RECEIVER_WWIDE_ZIP;
			$country = $this->RECEIVER_WWIDE_COUNTRY;
			$countryISOCode = $this->RECEIVER_WWIDE_COUNTRY_CODE;
		}
		$Zip = new ZipType($germany, $england, $other);
		$Origin = new CountryType($country, $countryISOCode, $state);
		$address = new NativeAddressType($streetName, $streetNumber, $careOfName, $Zip, $city, $district, $Origin, $floorNumber, $roomNumber, $languageCodeISO, $note);
		
		return $address;
	}
	
	
	public function createReceiverCommunicationType() {
		$phone=$this->RECEIVER_CONTACT_PHONE;
		$email=$this->RECEIVER_CONTACT_EMAIL;
		$fax=null;
		$mobile=null;
		$internet=null;
		$contactPerson=$this->RECEIVER_CONTACT_NAME;
		$communication = new CommunicationType($phone, $email, $fax, $mobile, $internet, $contactPerson);
		
		return $communication;
	}
	
	
	public function createDefaultShipmentDDRequest() {
		
		// Version erstellen	
		// ShipmentOrder erstellen
		$seqNumber = 1;
		$lblRespType = "URL";

		$shCompany = $this->createShipperCompany();
		$shAddress = $this->createShipperNativeAddressType();
		$shCommunication = $this->createShipperCommunicationType();
		$shVAT = null;
		$Shipper = new ShipperType($shCompany, $shAddress, $shCommunication, $shVAT);
		
		$ShipmentDetails = $this->createShipmentDetailsDDType();
		$rCompany = $this->createReceiverCompany(true);
		$rAddress = $this->createReceiverNativeAddressType(false);
		$rCommunication = $this->createReceiverCommunicationType();
		$rVAT = null;
		$Receiver = new ReceiverType($rCompany,$rAddress, null, null, $rCommunication, null);
		
		$ExportDocument = null;
		$Identity = null;
		$FurtherAddresses = null;
		
		$Shipment = new Shipment($ShipmentDetails, $Shipper, $Receiver, $ExportDocument, $Identity, null,$FurtherAddresses);		
		
		$Version = $this->createVersion();
		$shipmentOrderDDType = new ShipmentOrderDDType($seqNumber, $Shipment, null, $lblRespType);
		$createShipmentDDRequest = new CreateShipmentDDRequest($Version, $shipmentOrderDDType);

		return $createShipmentDDRequest;
	}
	
	
	public function createDefaultShipmentItemTDType() {
		$shipmentItem = new ShipmentItemType();
	
		$shipmentItem->HeightInCM = "15";
		$shipmentItem->LengthInCM = "30";
		$shipmentItem->WeightInKG = "3";
		$shipmentItem->WidthInCM = "30";		
		return $shipmentItem;
	}

	
	//Als Beispiel: shipmentDetails mit 1 shipmentItem
	public function createShipmentDetailsTDType() {
		$today = new DateTime('NOW');
		$today->add(new DateInterval('P2D'));
	
		$shipmentDetails = new ShipmentDetailsTDType();
		$shipmentDetails->ProductCode = $this->DD_PROD_CODE;
		$shipmentDetails->ShipmentDate = $today->format($this->SDF);
		
		$acc = new Account(TD_ACC_NUMBER_EXPRESS);
		$shipmentDetails->Account = $acc;
		$shipmentDetails->Dutiable = $this->TD_DUTIABLE;
		$shipmentDetails->DescriptionOfContent = $this->SHIPMENT_DESC;
	
		$shipmentDetails->ShipmentItem = $this->createDefaultShipmentItemTDType();
		$shipmentDetails->Description = $this->SHIPMENT_DESC;
		$shipmentDetails->ShipmentReference=$this->TD_SHIPMENT_REF;
		$shipmentDetails->DeclaredValueOfGoods=$this->TD_VALUE_GOODS;
		$shipmentDetails->DeclaredValueOfGoodsCurrency=$this->TD_CURRENCY;
		
		return $shipmentDetails;
	}
	
	
	public function createDefaultExportDocTDType ($date){
		$exportDoc = new ExportDocumentTDType();
		$exportDoc->InvoiceType=INVOICE_TYPE;
		$exportDoc->InvoiceDate = $date;
		$exportDoc->InvoiceNumber = $this->INVOICE_NUMBER;
		$exportDoc->ExportType = $this->EXPORT_TYPE;
		$exportDoc->SignerTitle = $this->SIGNER_TITLE;
		$exportDoc->ExportReason = $this->EXPORT_REASON;
	
		return $exportDoc;
	}
	
	
	public function createDefaultShipmentTDRequest() {
		
		$today = new DateTime('NOW');
	
		// Neue Abfrage
		$createShipmentTDRequest = new CreateShipmentTDRequest();
		
		// Version setzen	
		$createShipmentTDRequest->Version = $this->createVersion();
		// ShipmentOrder erstellen
		$shipmentOrderTDType = new ShipmentOrderTDType();
		$shipmentOrderTDType->SequenceNumber = "1";
		$shipment = new Shipment();
		$shipment->ShipmentDetails = $this->createShipmentDetailsTDType();
		$shipment->ExportDocument = $this->createDefaultExportDocTDType($today->format($this->SDF));
			
		$shipper = new ShipperType();
		$shipper->Company = $this->createShipperCompany();
		$shipper->Address = $this->createShipperNativeAddressType();
		$shipper->Communication = $this->createShipperCommunicationType();
		$shipment->Shipper = $shipper;
		
		$receiver = new ReceiverTDType();
		$receiverCompany = $this->createReceiverCompany(true);
		$receiver->Address = $this->createReceiverNativeAddressType(true);
		$receiver->Communication = $this->createReceiverCommunicationType();
		$shipment->Receiver = $receiver;
		
		$shipmentOrderTDType->LabelResponseType = "URL";	
		$shipmentOrderTDType->Shipment = $shipment;
		//ShipmentOrder übergeben
		$createShipmentTDRequest->ShipmentOrder = $shipmentOrderTDType;
	
		return $createShipmentTDRequest;
	}
	
	
	public function getDefaultLabelDDRequest($shipmentId) {
		$ddRequest = new GetLabelDDRequest();
		$ddRequest->Version = $this->createVersion();
		$shNumber = new ShipmentNumberType();
		if (shipmentId !='')
			$shNumber->shipmentNumber = $shipmentId;
		else
			$shNumber->shipmentNumber = $this->DUMMY_SHIPMENT_NUMBER;
		$ddRequest->ShipmentNumber = $shNumber;
		return $ddRequest;
	}
	
	
	public function getDeleteShipmentDDRequest($shipmentId) {
		$ddRequest = new DeleteShipmentDDRequest();
		$ddRequest->Version = $this->createVersion();
		$shNumber = new ShipmentNumberType();
		if (shipmentId !='')
			$shNumber->shipmentNumber = $shipmentId;
		else
			$shNumber->shipmentNumber = $this->DUMMY_SHIPMENT_NUMBER;
		$ddRequest->ShipmentNumber = $shNumber;
		return $ddRequest;
	}
	
	
	public function getDeleteShipmentTDRequest($shipmentId) {
		$tdRequest = new DeleteShipmentTDRequest();
		$tdRequest->Version = $this->createVersion();
		$shNumber = new ShipmentNumberType();
		if ($shipmentId !='')
			$shNumber->airwayBill= $shipmentId;
		else
			$shNumber->airwayBill = $this->DUMMY_AIRWAY_BILL;
	
		$tdRequest->ShipmentNumber = $shNumber;
		return $tdRequest;
	}
	
	
}