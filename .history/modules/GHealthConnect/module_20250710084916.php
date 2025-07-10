<?php

declare(strict_types=1);

class GoogleHealthConnector extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterHook('/hook/googlehealth');

        // Register all properties from form.json dynamically
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        foreach ($form['actions'][0]['tabs'] as $tab) {
            foreach ($tab['items'] as $item) {
                if ($item['type'] === 'CheckBox') {
                    $this->RegisterPropertyBoolean($item['name'], false);
                }
            }
        }
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Check if Connect service is available and set status accordingly
        if (strpos($this->GetHookURL(), 'http') === 0) {
            $this->SetStatus(102); // IS_ACTIVE
        } else {
            $this->SetStatus(201); // Inactive due to missing Connect
        }

        // Create custom variable profiles
        $this->CreateVariableProfiles();

        // Maintain all variables and categories based on the current configuration
        $this->MaintainAllVariables();
    }

    /**
     * This function is called when data is received via the WebHook.
     */
    public function ProcessHookData()
    {
        $this->SendDebug('Hook Called', 'Received data from Companion App', 0);

        // Get JSON payload from the HTTP POST request
        $payload = file_get_contents('php://input');
        if ($payload === false) {
            $this->SendDebug('Error', 'Failed to read php://input or payload is empty', 0);
            http_response_code(400); // Bad Request
            echo 'Empty payload';
            return;
        }

        $data = json_decode($payload, true);

        // Check if JSON is valid
        if ($data === null) {
            $this->SendDebug('Invalid JSON', 'Received non-JSON payload: ' . $payload, 0);
            http_response_code(400); // Bad Request
            echo 'Invalid JSON';
            return;
        }

        $this->SendDebug('Payload', json_encode($data), 0);

        // Process each data point from the payload
        // The key (e.g., "steps", "weight") should be sent by the companion app
        foreach ($data as $key => $record) {
            // Create the property name from the key (e.g., "steps" -> "EnableSteps")
            $propertyName = 'Enable' . str_replace('_', '', ucwords($key, '_'));
            
            // Check if the user wants to sync this data type
            if ($this->ReadPropertyBoolean($propertyName)) {
                // Create the handler method name (e.g., "steps" -> "ProcessStepsData")
                $handlerMethod = 'Process' . str_replace('_', '', ucwords($key, '_')) . 'Data';
                if (method_exists($this, $handlerMethod)) {
                    // Call the specific handler for this data type
                    $this->$handlerMethod($record);
                } else {
                    $this->SendDebug('Missing Handler', "No handler method found for key: '$key' (expected method: '$handlerMethod')", 0);
                }
            }
        }

        http_response_code(200); // OK
        echo 'Data received';
    }

    private function GetHookURL(): string
    {
        // We need to find the correct IP-Symcon Connect Service instance
        $connectIDs = IPS_GetInstanceListByModuleID('{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}');
        if (isset($connectIDs[0])) {
            $connectInstance = $connectIDs[0];
            if (IPS_GetInstance($connectInstance)['InstanceStatus'] == 102) { // 102 == IS_ACTIVE
                return IPS_GetProperty($connectInstance, 'ConnectURL') . '/hook/googlehealth';
            }
        }
        return 'IP-Symcon Connect Dienst nicht gefunden/aktiviert!';
    }

    // --- Data Processing Handlers ---

    private function ProcessStepsData($data)
    {
        $this->SetValue('Steps', $data['value']);
    }

    private function ProcessDistanceData($data)
    {
        $this->SetValue('Distance', $data['value'] / 1000); // Convert meters to km
    }

    private function ProcessTotalCaloriesBurnedData($data)
    {
        $this->SetValue('TotalCaloriesBurned', $data['value']);
    }

    private function ProcessActiveCaloriesBurnedData($data)
    {
        $this->SetValue('ActiveCaloriesBurned', $data['value']);
    }

    private function ProcessWeightData($data)
    {
        $this->SetValue('Weight', $data['value']);
    }

    private function ProcessHeartRateData($data)
    {
        $this->SetValue('HeartRate', $data['value']);
    }

    private function ProcessRestingHeartRateData($data)
    {
        $this->SetValue('RestingHeartRate', $data['value']);
    }

    private function ProcessBloodPressureData($data)
    {
        $this->SetValue('BloodPressureSystolic', $data['systolic']);
        $this->SetValue('BloodPressureDiastolic', $data['diastolic']);
    }

    private function ProcessBodyTemperatureData($data)
    {
        $this->SetValue('BodyTemperature', $data['value']);
    }

    private function ProcessOxygenSaturationData($data)
    {
        $this->SetValue('OxygenSaturation', $data['value']);
    }

    private function ProcessSleepSessionData($data)
    {
        $this->SetValue('SleepDuration', $data['duration_total_minutes']);
        $this->SetValue('SleepDurationDeep', $data['duration_deep_minutes']);
        $this->SetValue('SleepDurationLight', $data['duration_light_minutes']);
        $this->SetValue('SleepDurationRem', $data['duration_rem_minutes']);
        $this->SetValue('SleepDurationAwake', $data['duration_awake_minutes']);
        $this->SetValue('SleepStart', $data['start_time']);
        $this->SetValue('SleepEnd', $data['end_time']);
    }

    // --- Helper Functions ---

    private function MaintainAllVariables()
    {
        // Activity
        $this->MaintainVariable('Steps', 'Schritte', VARIABLETYPE_INTEGER, '~Steps', 10, $this->ReadPropertyBoolean('EnableSteps'));
        $this->MaintainVariable('Distance', 'Distanz', VARIABLETYPE_FLOAT, '~Distance.km', 11, $this->ReadPropertyBoolean('EnableDistance'));
        $this->MaintainVariable('TotalCaloriesBurned', 'Kalorien (Gesamt)', VARIABLETYPE_FLOAT, 'GHC.kcal', 12, $this->ReadPropertyBoolean('EnableTotalCaloriesBurned'));
        $this->MaintainVariable('ActiveCaloriesBurned', 'Kalorien (Aktiv)', VARIABLETYPE_FLOAT, 'GHC.kcal', 13, $this->ReadPropertyBoolean('EnableActiveCaloriesBurned'));

        // Body
        $this->MaintainVariable('Weight', 'Gewicht', VARIABLETYPE_FLOAT, '~Weight.kg', 20, $this->ReadPropertyBoolean('EnableWeight'));

        // Vitals
        $this->MaintainVariable('HeartRate', 'Herzfrequenz', VARIABLETYPE_INTEGER, '~Heartbeat', 30, $this->ReadPropertyBoolean('EnableHeartRate'));
        $this->MaintainVariable('RestingHeartRate', 'Ruhepuls', VARIABLETYPE_INTEGER, '~Heartbeat', 31, $this->ReadPropertyBoolean('EnableRestingHeartRate'));
        $this->MaintainVariable('BodyTemperature', 'Körpertemperatur', VARIABLETYPE_FLOAT, '~Temperature', 41, $this->ReadPropertyBoolean('EnableBodyTemperature'));
        $this->MaintainVariable('OxygenSaturation', 'Sauerstoffsättigung', VARIABLETYPE_FLOAT, 'GHC.Percent', 42, $this->ReadPropertyBoolean('EnableOxygenSaturation'));

        // Blood Pressure (Category)
        $this->MaintainCategory('BloodPressure', 'Blutdruck', 40, $this->ReadPropertyBoolean('EnableBloodPressure'));
        if ($this->ReadPropertyBoolean('EnableBloodPressure')) {
            $catID = $this->GetIDForIdent('BloodPressure');
            $this->MaintainVariable('BloodPressureSystolic', 'Systolisch', VARIABLETYPE_INTEGER, 'GHC.mmHg', 1, true, $catID);
            $this->MaintainVariable('BloodPressureDiastolic', 'Diastolisch', VARIABLETYPE_INTEGER, 'GHC.mmHg', 2, true, $catID);
        }

        // Sleep (Category)
        $this->MaintainCategory('Sleep', 'Schlaf', 100, $this->ReadPropertyBoolean('EnableSleepSession'));
        if ($this->ReadPropertyBoolean('EnableSleepSession')) {
            $catID = $this->GetIDForIdent('Sleep');
            $this->MaintainVariable('SleepDuration', 'Schlafdauer (Gesamt)', VARIABLETYPE_INTEGER, '~Duration.min', 1, true, $catID);
            $this->MaintainVariable('SleepDurationDeep', 'Tiefschlaf', VARIABLETYPE_INTEGER, '~Duration.min', 2, true, $catID);
            $this->MaintainVariable('SleepDurationLight', 'Leichtschlaf', VARIABLETYPE_INTEGER, '~Duration.min', 3, true, $catID);
            $this->MaintainVariable('SleepDurationRem', 'REM-Schlaf', VARIABLETYPE_INTEGER, '~Duration.min', 4, true, $catID);
            $this->MaintainVariable('SleepDurationAwake', 'Wachzeit', VARIABLETYPE_INTEGER, '~Duration.min', 5, true, $catID);
            $this->MaintainVariable('SleepStart', 'Schlafbeginn', VARIABLETYPE_STRING, '', 6, true, $catID);
            $this->MaintainVariable('SleepEnd', 'Schlafende', VARIABLETYPE_STRING, '', 7, true, $catID);
        }
    }

    // Override MaintainVariable to accept a parentID
    public function MaintainVariable(string $Ident, string $Name, int $Type, string $Profile, int $Position, bool $Keep, int $ParentID = 0)
    {
        parent::MaintainVariable($Ident, $Name, $Type, $Profile, $Position, $Keep);
        if ($Keep && $ParentID !== 0) {
            IPS_SetParent($this->GetIDForIdent($Ident), $ParentID);
        }
    }

    public function MaintainCategory(string $Ident, string $Name, int $Position, bool $Keep)
    {
        $catID = @$this->GetIDForIdent($Ident);

        if ($Keep && $catID === false) {
            $catID = IPS_CreateCategory();
            IPS_SetIdent($catID, $Ident);
            IPS_SetParent($catID, $this->InstanceID);
        }

        if (!$Keep && $catID !== false) {
            IPS_DeleteCategory($catID);
            return;
        }

        if ($catID !== false) {
            IPS_SetName($catID, $Name);
            IPS_SetPosition($catID, $Position);
        }
    }

    /**
     * Creates custom variable profiles if they don't exist.
     */
    private function CreateVariableProfiles()
    {
        if (!IPS_VariableProfileExists('GHC.mmHg')) {
            IPS_CreateVariableProfile('GHC.mmHg', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileText('GHC.mmHg', '', ' mmHg');
            IPS_SetVariableProfileValues('GHC.mmHg', 40, 200, 1);
        }

        // Profile for SpO2
        if (!IPS_VariableProfileExists('GHC.Percent')) {
            IPS_CreateVariableProfile('GHC.Percent', VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileText('GHC.Percent', '', ' %');
            IPS_SetVariableProfileDigits('GHC.Percent', 1);
            IPS_SetVariableProfileValues('GHC.Percent', 80, 100, 0.1);
            IPS_SetVariableProfileIcon('GHC.Percent', 'Drops');
        }

        // Profile for kcal
        if (!IPS_VariableProfileExists('GHC.kcal')) {
            IPS_CreateVariableProfile('GHC.kcal', VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileText('GHC.kcal', '', ' kcal');
            IPS_SetVariableProfileDigits('GHC.kcal', 0);
            IPS_SetVariableProfileIcon('GHC.kcal', 'Flame');
        }
    }

    /**
     * Overwrites the default GetConfigurationForm to dynamically update the WebHook URL.
     * @return string
     */
    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $form['elements'][1]['label'] = $this->GetHookURL();
        return json_encode($form);
    }
}
```

### Nächste Schritte

1.  **`form.json` erstellen**: Erstelle die Konfigurationsdatei, in der der Benutzer die Checkboxen (`EnableSteps`, `EnableWeight` etc.) anhaken kann.
2.  **`module.php` vervollständigen**:
   *   Füge für jeden Datentyp eine `RegisterPropertyBoolean`-Zeile in `Create()` hinzu.
   *   Füge für jeden Datentyp eine `MaintainVariable`-Zeile in `MaintainVariables()` hinzu.
   *   Implementiere für jeden Datentyp eine `Process...Data`-Funktion, um die Daten zu verarbeiten und mit `SetValue` in die Variable zu schreiben.
3.  **Companion App**: Definiere (oder entwickle) die Android App, die die Daten im erwarteten JSON-Format an den WebHook sendet.

Dieses Gerüst gibt dir eine hervorragende Ausgangsbasis. Viel Erfolg bei der weiteren Entwicklung!

<!--
[PROMPT_SUGGESTION]Erstelle jetzt die passende `form.json` und `locale.json` für die in `module.php` definierten Eigenschaften.[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]Vervollständige die `module.php` mit allen Daten-Handlern und der Logik zum Erstellen der Variablen, basierend auf der `form.json` aus dem Kontext.[/PROMPT_SUGGESTION]
