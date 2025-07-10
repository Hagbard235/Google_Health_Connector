<?php

declare(strict_types=1);

class GoogleHealthConnector extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // Register the WebHook that will receive the data from the companion app
        $this->RegisterHook('/hook/googlehealth');

        // --- Register Properties ---
        // These properties will correspond to the CheckBoxes in your form.json
        // I'm adding a few examples here. You should add one for each data type.
        $this->RegisterPropertyBoolean('EnableSteps', false);
        $this->RegisterPropertyBoolean('EnableWeight', false);
        $this->RegisterPropertyBoolean('EnableHeartRate', false);
        $this->RegisterPropertyBoolean('EnableBloodPressure', false);
        $this->RegisterPropertyBoolean('EnableSleepSession', false);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Set status to active
        $this->SetStatus(102); // IS_ACTIVE

        // This is where you would create/delete variables based on the user's selection
        $this->MaintainVariables();
    }

    /**
     * This function is called when data is received via the WebHook.
     */
    public function ProcessHookData()
    {
        $this->SendDebug('Hook Called', 'Received data from Companion App', 0);

        // Get JSON payload from the HTTP POST request
        $payload = file_get_contents('php://input');
        if ($payload === false || $payload === '') {
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

    /**
     * This function creates or deletes variables based on the instance configuration.
     */
    private function MaintainVariables()
    {
        // Example for creating/deleting the 'Steps' variable
        $this->MaintainVariable('Steps', 'Schritte', VARIABLETYPE_INTEGER, '~Steps', 10, $this->ReadPropertyBoolean('EnableSteps'));
        
        // Example for 'Weight'
        $this->MaintainVariable('Weight', 'Gewicht', VARIABLETYPE_FLOAT, '~Weight.kg', 20, $this->ReadPropertyBoolean('EnableWeight'));

        // TODO: Add MaintainVariable calls for all other data types here.
    }

    // --- Data Processing Handlers ---
    // For each data type, you need a method that processes the incoming data.

    private function ProcessStepsData($data)
    {
        $this->SendDebug(__FUNCTION__, json_encode($data), 0);
        // Example: $data could be ['value' => 1234, 'timestamp' => '...']
        $this->SetValue('Steps', $data['value']);
    }

    private function ProcessWeightData($data)
    {
        $this->SendDebug(__FUNCTION__, json_encode($data), 0);
        // Example: $data could be ['value' => 80.5, 'timestamp' => '...']
        $this->SetValue('Weight', $data['value']);
    }

    // TODO: Add a Process...Data method for each data type you want to support.
    // private function ProcessHeartRateData($data) { ... }
    // private function ProcessBloodPressureData($data) { ... }
    // private function ProcessSleepSessionData($data) { ... }

    /**
     * Returns the full WebHook URL for the companion app.
     * @return string
     */
    public function GetHookURL(): string
    {
        // We need to find the IP-Symcon Connect Service instance
        $connectIDs = IPS_GetInstanceListByModuleID('{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}');
        if (isset($connectIDs[0]) && IPS_GetInstance($connectIDs[0])['InstanceStatus'] == 102) {
            return IPS_GetProperty($connectIDs[0], 'ConnectURL') . '/hook/googlehealth';
        }
        return 'IP-Symcon Connect Dienst nicht gefunden/aktiviert!';
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
