<?php
class GHC_Module extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        //Properties
        $this->RegisterPropertyString('Token', '');
        $this->RegisterPropertyInteger('Interval', 60); // minutes
        $this->RegisterPropertyString('DataTypes', ''); // comma separated list
        //Timer
        $this->RegisterTimer('UpdateData', 0, 'GHC_Update($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $interval = $this->ReadPropertyInteger('Interval');
        $this->SetTimerInterval('UpdateData', $interval * 60 * 1000);
    }

    public function RequestAction($ident, $value)
    {
        switch ($ident) {
            case 'Update':
                $this->Update();
                break;
            default:
                throw new Exception('Invalid ident');
        }
    }

    public function GetConfigurationForm()
    {
        return json_encode([
            'elements' => [
                ['type' => 'ValidationTextBox', 'name' => 'Token', 'caption' => 'Token'],
                ['type' => 'NumberSpinner', 'name' => 'Interval', 'caption' => 'Update interval (min)'],
                ['type' => 'Select', 'name' => 'DataTypes', 'caption' => 'Data Types', 'options' => $this->GetDataTypeOptions(), 'multiple' => true]
            ],
            'actions' => [
                ['type' => 'Button', 'caption' => 'Update now', 'onClick' => 'GHC_Update($id);']
            ]
        ]);
    }

    private function GetDataTypeOptions()
    {
        $types = [
            'steps',
            'sleep',
            'heart_rate',
            'weight'
        ];
        $options = [];
        foreach ($types as $type) {
            $options[] = ['caption' => ucfirst($type), 'value' => $type];
        }
        return $options;
    }

    public function Update()
    {
        $this->FetchData();
    }

    private function FetchData()
    {
        $token = $this->ReadPropertyString('Token');
        $types = explode(',', $this->ReadPropertyString('DataTypes'));
        foreach ($types as $type) {
            $type = trim($type);
            if ($type === '') {
                continue;
            }
            $data = $this->RequestHealthData($type, $token);
            if ($data === null) {
                continue;
            }
            $ident = strtoupper($type);
            if (!$this->VariableExistsByIdent($ident)) {
                $this->RegisterVariableString($ident, ucfirst($type));
            }
            $this->SetValue($ident, json_encode($data));
        }
    }

    private function VariableExistsByIdent(string $ident): bool
    {
        foreach ($this->GetChildrenIDs($this->InstanceID) as $id) {
            if (IPS_GetObject($id)['ObjectIdent'] === $ident) {
                return true;
            }
        }
        return false;
    }

    private function RequestHealthData(string $type, string $token)
    {
        $curl = curl_init('https://www.googleapis.com/health/v1/' . $type);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token
        ]);
        $result = curl_exec($curl);
        if ($result === false) {
            IPS_LogMessage('GHC_Module', 'HTTP request failed: ' . curl_error($curl));
            curl_close($curl);
            return null;
        }
        curl_close($curl);
        return json_decode($result, true);
    }
}

if (!function_exists('GHC_Update')) {
    function GHC_Update(int $id)
    {
        $instance = IPS_GetInstance($id);
        if ($instance['ModuleID'] === '{EAD5CC92-900C-427C-8B36-82C17F1B66E6}') {
            IPS_RequestAction($id, 'Update', 0);
        }
    }
}

