<?php

declare(strict_types=1);

// Generell funktions
require_once __DIR__ . '/../libs/_traits.php';

/**
 * CLASS WallThermostat
 */
class WallThermostat extends IPSModule
{
    use DebugHelper;
    use VariableHelper;

    // Constants
    public const WT_DEVICE = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}';
    public const WT_PROFILE = 'ACTIVE_PROFILE';
    public const WT_MODE = 'SET_POINT_MODE';

    /**
     * Create.
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // Thermostat
        $this->RegisterPropertyInteger('Thermostat', 1);

        // Radiators
        $this->RegisterPropertyInteger('Radiator1', 1);
        $this->RegisterPropertyInteger('Radiator2', 1);
        $this->RegisterPropertyInteger('Radiator3', 1);
        $this->RegisterPropertyInteger('Radiator4', 1);
        $this->RegisterPropertyInteger('Radiator5', 1);
        $this->RegisterPropertyInteger('Radiator6', 1);
        $this->RegisterPropertyInteger('Radiator7', 1);
        $this->RegisterPropertyInteger('Radiator8', 1);

        // Settings
        $this->RegisterPropertyBoolean('SyncProfile', false);
        $this->RegisterPropertyBoolean('SyncMode', false);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Aktualisiere registrierte Nachrichten
        foreach ($this->GetMessageList() as $senderID => $messageIDs) {
            foreach ($messageIDs as $messageID) {
                $this->UnregisterMessage($senderID, $messageID);
            }
        }

        $thermostat = $this->ReadPropertyInteger('Thermostat');
        if (IPS_InstanceExists($thermostat)) {
            $vid = @GetObjectByIdent($thermostat, self::WT_PROFILE);
            if ($vid !== false) {
                $this->RegisterMessage($vid, VM_UPDATE);
            }
            $vid = @GetObjectByIdent($thermostat, self::WT_MODE);
            if ($vid !== false) {
                $this->RegisterMessage($vid, VM_UPDATE);
            }
        }

        // Statusvariable (SyncProfile)
        $esp = @$this->GetIDForIdent('SyncProfile');
        // Switches (Settings)
        $profile = $this->ReadPropertyBoolean('SyncProfile');
        $this->MaintainVariable('SyncProfile', $this->Translate('Profile'), VARIABLETYPE_BOOLEAN, '~Switch', 1, $profile);
        if ($profile && !$esp) {
            $this->SetValueBoolean('SyncProfile', true);
        }

        // Statusvariable (SyncMode)
        $esm = @$this->GetIDForIdent('SyncMode');
        $mode = $this->ReadPropertyBoolean('SyncMode');
        $this->MaintainVariable('SyncMode', $this->Translate('Mode'), VARIABLETYPE_BOOLEAN, '~Switch', 2, $mode);
        if ($mode && !$esm) {
            $this->SetValueBoolean('SyncMode', true);
        }
    }

    /**
     * MessageSink - internal SDK funktion.
     *
     * @param mixed $timeStamp Message timeStamp
     * @param mixed $senderID Sender ID
     * @param mixed $message Message type
     * @param mixed $data data[0] = new value, data[1] = value changed, data[2] = old value, data[3] = timestamp
     */
    public function MessageSink($timeStamp, $senderID, $message, $data)
    {
        //$this->SendDebug(__FUNCTION__, 'SenderId: '. $senderID . ' Data: ' . print_r($data, true), 0);
        $profileID = 1;
        $modeID = 1;
        $thermostat = $this->ReadPropertyInteger('Thermostat');
        if (IPS_InstanceExists($thermostat)) {
            $vid = @GetObjectByIdent($thermostat, self::WT_PROFILE);
            if ($vid !== false) {
                $profileID = $vid;
            }
            $vid = @GetObjectByIdent($thermostat, self::WT_MODE);
            if ($vid !== false) {
                $modeID = $vid;
            }
        }
        switch ($message) {
            case VM_UPDATE:
                if ($senderID == $profileID) {
                    // state changes ?
                    if ($data[1] == true) {
                        $this->SendDebug(__FUNCTION__, 'Profile switched to <' . $data[0] . '>');
                        $this->Profile($data[0]);
                    }
                }
                if ($senderID == $modeID) {
                    // state changes ?
                    if ($data[1] == true) {
                        $this->SendDebug(__FUNCTION__, 'Mode switched to <' . $data[0] . '>');
                        $this->Mode($data[0]);
                    }
                }
                break;

        }
    }

    /**
     * Profile - switch profile.
     *
     * @* @param int $value Value of the new selected profile.
     */
    private function Profile($value)
    {
        // Safty check
        if ($value < 1 || $value > 3) {
            return;
        }
        // Switch all radiators
        for ($i = 1; $i <= 8; $i++) {
            $radiator = $this->ReadPropertyInteger('Radiator' . $i);
            $this->SendDebug(__FUNCTION__, 'Radiator' . $i . ': ID => ' . $radiator);
            if (IPS_InstanceExists($radiator)) {
                $ret = HM_WriteValueInteger($radiator, 'ACTIVE_PROFILE', $value);
            }
        }
    }

    /**
     * Mode - switch mode.
     *
     * @* @param int $value Value of the new selected mode.
     */
    private function Mode($value)
    {
        // Safty check
        if ($value < 0 || $value > 3) {
            return;
        }
        // Switch all radiators
        for ($i = 1; $i <= 8; $i++) {
            $radiator = $this->ReadPropertyInteger('Radiator' . $i);
            $this->SendDebug(__FUNCTION__, 'Radiator' . $i . ': ID => ' . $radiator);
            if (IPS_InstanceExists($radiator)) {
                $ret = HM_WriteValueInteger($radiator, 'CONTROL_MODE', $value);
            }
        }
    }
}
