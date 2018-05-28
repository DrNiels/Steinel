<?
class SteinelHPD2IP extends IPSModule {
    
    public function Create(){
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString("IPAdress", "0.0.0.0");
        $this->RegisterPropertyString("AdminPassword", "");
        $this->RegisterPropertyInteger("UpdateInterval", 0);

        if (!IPS_VariableProfileExists("ST_DetectedPersons")) {
            IPS_CreateVariableProfile("ST_DetectedPersons", 1);
            IPS_SetVariableProfileAssociation("ST_DetectedPersons", -1, $this->Translate("Inactive"), "", -1);
            IPS_SetVariableProfileAssociation("ST_DetectedPersons", 0, "%d", "", -1);
        }

        $this->RegisterVariableInteger("DetectedPersons", $this->Translate("Detected Persons"), "ST_DetectedPersons", 0);
        $this->RegisterVariableBoolean("LED", $this->Translate("LED"), "~Switch", false);
        $this->RegisterVariableInteger("DetectedPersonsZone1", $this->Translate("Detected Persons - Zone 1"), "ST_DetectedPersons", -1);
        $this->RegisterVariableInteger("DetectedPersonsZone2", $this->Translate("Detected Persons - Zone 2"), "ST_DetectedPersons", -1);
        $this->RegisterVariableInteger("DetectedPersonsZone3", $this->Translate("Detected Persons - Zone 3"), "ST_DetectedPersons", -1);
        $this->RegisterVariableInteger("DetectedPersonsZone4", $this->Translate("Detected Persons - Zone 4"), "ST_DetectedPersons", -1);
        $this->RegisterVariableInteger("DetectedPersonsZone5", $this->Translate("Detected Persons - Zone 5"), "ST_DetectedPersons", -1);
        $this->RegisterVariableInteger("Illuminance", $this->Translate("Illuminance"), "~Illumination", 0);
        $this->RegisterVariableFloat("Temperature", $this->Translate("Temperature"), "~Temperature", 0);
        $this->RegisterVariableFloat("Humidity", $this->Translate("Humidity"), "~Humidity.F", 0);

        $this->RegisterTimer("Update", 0, "ST_UpdateData(" . $this->InstanceID .");");
    }

    public function Destroy(){
        //Never delete this line!
        parent::Destroy();
        
    }

    public function ApplyChanges(){
        //Never delete this line!
        parent::ApplyChanges();

        $this->SetTimerInterval("Update", $this->ReadPropertyInteger("UpdateInterval") * 1000);
    }
    
    public function UpdateData() {
        $options = [
            'http'=> [
                'method'=>"GET",
                'header'=>"Authorization: Basic " . base64_encode('admin:' . $this->ReadPropertyString("AdminPassword")) . "\r\n"
            ]
        ];

        try {
            $data = @file_get_contents("http://" . $this->ReadPropertyString("IPAdress") . "/api/sensorstatus.php", false, stream_context_create($options));

            if ($data === false) {
                throw new Exception(print_r(error_get_last(), true));
            }

            $this->SendDebug($this->Translate("Received Data"), $data, 0);
            $jsonData = json_decode($data, true);
            if (isset($jsonData["IrLedOn"])) {
                SetValue(IPS_GetObjectIDByIdent("LED", $this->InstanceID), $jsonData["IrLedOn"] == 1);
            }
            if (isset($jsonData["DetectedPersons"])) {
                SetValue(IPS_GetObjectIDByIdent("DetectedPersons", $this->InstanceID), $jsonData["DetectedPersons"]);
            }
            if (isset($jsonData["GlobalIlluminanceLux"])) {
                SetValue(IPS_GetObjectIDByIdent("Illuminance", $this->InstanceID), $jsonData["GlobalIlluminanceLux"]);
            }
            if (isset($jsonData["Temperature"])) {
                SetValue(IPS_GetObjectIDByIdent("Temperature", $this->InstanceID), floatval($jsonData["Temperature"]));
            }
            if (isset($jsonData["Humidity"])) {
                SetValue(IPS_GetObjectIDByIdent("Humidity", $this->InstanceID), floatval($jsonData["Humidity"]));
            }

            if (isset($jsonData["DetectionZonesPresent"]) && isset($jsonData["DetectedPersonsZone"]) && sizeof($jsonData["DetectedPersonsZone"]) == 5) {
                $numberZones = $jsonData["DetectionZonesPresent"];

                for ($i = 0; $i < 5; $i++) {
                    if ($i >= $numberZones) {
                        SetValue(IPS_GetObjectIDByIdent("DetectedPersonsZone" . ($i + 1), $this->InstanceID), -1);
                    } else {
                        SetValue(IPS_GetObjectIDByIdent("DetectedPersonsZone" . ($i + 1), $this->InstanceID), $jsonData["DetectedPersonsZone"][$i]);
                    }
                }
            }
        }
        catch (Exception $exception) {
            $this->SendDebug($this->Translate("Getting data failed"), $exception->getMessage(), 0);
            echo $this->Translate("Could not read data from device: ") . $exception->getMessage();
        }
    }
}

?>