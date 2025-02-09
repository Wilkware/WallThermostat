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
    use EventHelper;
    use VariableHelper;
    use VersionHelper;

    // Constants
    public const WT_DEVICE = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}';
    public const WT_PROFILE = 'ACTIVE_PROFILE';
    public const WT_MODE = 'SET_POINT_MODE';
    public const WT_ACTUAL = 'ACTUAL_TEMPERATURE';
    public const WT_TEMP = 'SET_POINT_TEMPERATURE';
    public const WT_STATE = 'VALVE_STATE';

    public const WT_COOLING = 'COOLING';
    public const WT_HEATING = 'HEATING';
    public const WT_VENTILE = 'VENTILE';

    // Min IPS Object ID
    private const IPS_MIN_ID = 10000;

    // Schedule constant
    private const WT_SCHEDULE_CONTROL_OFF = 1;
    private const WT_SCHEDULE_CONTROL_ON = 2;
    private const WT_SCHEDULE_CONTROL_IDENT = 'circuit_control';
    private const WT_SCHEDULE_CONTROL_SWITCH = [
        self::WT_SCHEDULE_CONTROL_OFF => ['Inaktive', 0xFF0000, "IPS_RequestAction(\$_IPS['TARGET'], 'schedule_control', \$_IPS['ACTION']);"],
        self::WT_SCHEDULE_CONTROL_ON  => ['Aktive', 0x00FF00, "IPS_RequestAction(\$_IPS['TARGET'], 'schedule_control', \$_IPS['ACTION']);"],
    ];

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     *
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

        // Check
        $this->RegisterPropertyBoolean('CheckCooling', false);
        $this->RegisterPropertyFloat('DegreeCooling', -2.0);
        $this->RegisterPropertyBoolean('CheckHeating', false);
        $this->RegisterPropertyFloat('DegreeHeating', 2.0);
        $this->RegisterPropertyBoolean('CheckVentile', false);
        $this->RegisterPropertyInteger('StateVentile', 1);

        // Schedule
        $this->RegisterPropertyInteger('TestInterval', 120);
        $this->RegisterPropertyInteger('TestSchedule', 1);

        // Dashboard variables
        $this->RegisterPropertyInteger('DashboardMessage', 0);
        $this->RegisterPropertyInteger('DashboardLifetime', 120);
        $this->RegisterPropertyInteger('NotificationMessage', 0);
        $this->RegisterPropertyString('RoomName', $this->Translate('Unknown'));
        $this->RegisterPropertyString('TextCooldown', $this->Translate('%R: Cooling down too much!'));
        $this->RegisterPropertyString('TextHeatup', $this->Translate('%R: Heating up too much!'));
        $this->RegisterPropertyString('TextValvestate', $this->Translate('%R: Valve not ready for operation!'));
        $this->RegisterPropertyString('TitleMessage', $this->Translate('Wall Thermostat'));
        $this->RegisterPropertyInteger('InstanceVisu', 0);
        $this->RegisterPropertyInteger('ScriptMessage', 0);

        // Settings
        $this->RegisterPropertyBoolean('SyncProfile', false);
        $this->RegisterPropertyBoolean('SyncMode', false);

        // Register update timer
        $this->RegisterTimer('TimerControl', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "control", "");');
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting IP-Symcon.
     *
     */
    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        //Delete all references in order to readd them
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        //Delete all registrations in order to readd them
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        // Register references for variables
        $thermostat = $this->ReadPropertyInteger('Thermostat');
        if ($thermostat >= self::IPS_MIN_ID) {
            if (IPS_InstanceExists($thermostat)) {
                $this->RegisterReference($thermostat);
            } else {
                $this->SendDebug(__FUNCTION__, 'Instance does not exist - Variable: ' . $thermostat);
                $this->SetStatus(104);
                return;
            }
        }
        for ($i = 1; $i <= 8; $i++) {
            $radiator = $this->ReadPropertyInteger('Radiator' . $i);
            if ($radiator >= self::IPS_MIN_ID) {
                if (IPS_InstanceExists($radiator)) {
                    $this->RegisterReference($radiator);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Instance does not exist - Variable: ' . $radiator);
                    $this->SetStatus(104);
                    return;
                }
            }
        }
        $visu = $this->ReadPropertyInteger('InstanceVisu');
        if ($visu >= self::IPS_MIN_ID) {
            if (IPS_InstanceExists($visu)) {
                $this->RegisterReference($visu);
            } else {
                $this->SendDebug(__FUNCTION__, 'Instance does not exist - Variable: ' . $visu);
                $this->SetStatus(104);
                return;
            }
        }
        $schedule = $this->ReadPropertyInteger('TestSchedule');
        if ($schedule >= self::IPS_MIN_ID) {
            if (IPS_EventExists($schedule)) {
                $this->RegisterReference($schedule);
            } else {
                $this->SendDebug(__FUNCTION__, 'Event does not exist - Variable: ' . $schedule);
                $this->SetStatus(104);
                return;
            }
        }
        $script = $this->ReadPropertyInteger('ScriptMessage');
        if ($script >= self::IPS_MIN_ID) {
            if (IPS_ScriptExists($script)) {
                $this->RegisterReference($script);
            } else {
                $this->SendDebug(__FUNCTION__, 'Script does not exist - Variable: ' . $script);
                $this->SetStatus(104);
                return;
            }
        }

        // Register message update for variables
        $vid = @GetObjectByIdent($thermostat, self::WT_PROFILE);
        if ($vid !== false) {
            $this->RegisterMessage($vid, VM_UPDATE);
        }
        $vid = @GetObjectByIdent($thermostat, self::WT_MODE);
        if ($vid !== false) {
            $this->RegisterMessage($vid, VM_UPDATE);
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

        // Control everything?
        $cooling = $this->ReadPropertyBoolean('CheckCooling');
        $heating = $this->ReadPropertyBoolean('CheckHeating');
        $ventile = $this->ReadPropertyBoolean('CheckVentile');
        if ($cooling || $heating || $ventile) {
            // Shedule Timer
            $interval = $this->ReadPropertyInteger('TestInterval');
            // Timer active?
            if ($interval > 0) {
                // Timer solo or schedule?
                if ($schedule < self::IPS_MIN_ID) {
                    $this->SetTimerInterval('TimerControl', (60 * 1000 * $interval));
                } else {
                    $wsi = $this->GetWeeklyScheduleInfo($schedule, time(), true);
                    $this->SendDebug(__FUNCTION__, $wsi);
                    if ($wsi['ActionID'] == self::WT_SCHEDULE_CONTROL_ON) {
                        $this->SetTimerInterval('TimerControl', (60 * 1000 * $interval));
                    }
                }
            } else {
                // Timer Reset
                $this->SetTimerInterval('TimerControl', 0);
            }
        } else {
            // Timer Reset
            $this->SetTimerInterval('TimerControl', 0);
        }
        $this->SetStatus(102);
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
     * Is called when, for example, a button is clicked in the visualization.
     *
     *  @param string $ident Ident of the variable
     *  @param string $value The value to be set
     */
    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug(__FUNCTION__, $ident . ' => ' . $value);
        // Ident == OnXxxxxYyyyy
        switch ($ident) {
            case 'create_schedule':
                $this->CreateSchedule();
                break;
            case 'schedule_control':
                $this->ScheduleControl($value);
                break;
            case 'control':
                $this->Control();
                break;
            default:
                break;
                //eval('$this->' . $ident . '(\'' . $value . '\');');
        }
        //return true;
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

    /**
     * Control
     *
     */
    private function Control()
    {
        // What to check?
        $cooling = $this->ReadPropertyBoolean('CheckCooling');
        $heating = $this->ReadPropertyBoolean('CheckHeating');
        $ventile = $this->ReadPropertyBoolean('CheckVentile');
        // Actual & Shall temperature
        $at = 100.0;
        $st = 100.0;
        // for both the same
        if ($cooling || $heating) {
            $thermostat = $this->ReadPropertyInteger('Thermostat');
            $vid = @GetObjectByIdent($thermostat, self::WT_ACTUAL);
            if ($vid !== false) {
                $at = GetValueFloat($vid);
            }
            $vid = @GetObjectByIdent($thermostat, self::WT_TEMP);
            if ($vid !== false) {
                $st = GetValueFloat($vid);
            }
        }
        $this->SendDebug(__FUNCTION__, 'ACTUAL: ' . $at . ', TARGET: ' . $st);
        // frist "Cool down"
        if ($cooling) {
            $dc = $this->ReadPropertyFloat('DegreeCooling');
            if ($at < $dc + $st) {
                $this->SendMessage(self::WT_COOLING);
            }
        }
        // second "Heat up"
        if ($heating) {
            $dh = $this->ReadPropertyFloat('DegreeHeating');
            if ($st + $dh < $at) {
                $this->SendMessage(self::WT_HEATING);
            }
        }
        // third "Valve state"
        if ($ventile) {
            $sv = $this->ReadPropertyInteger('StateVentile');
            // Go through all radiators
            $sm = false;
            for ($i = 1; $i <= 8; $i++) {
                $vs = -1;
                $radiator = $this->ReadPropertyInteger('Radiator' . $i);
                if ($radiator >= self::IPS_MIN_ID) {
                    $vid = @GetObjectByIdent($radiator, self::WT_STATE);
                    if ($vid !== false) {
                        $vs = GetValueInteger($vid);
                    }
                    $this->SendDebug(__FUNCTION__, 'RADIATOR_' . $i . ': ' . $vs . ', SV: ' . $sv);
                    // ready (4)
                    if ($sv == 0) {
                        if ($vs != 4) {
                            $sm = true;
                        }
                    } else { // error (5,6,7)
                        if (($vs == 5) || ($vs == 6) || ($vs == 7)) {
                            $sm = true;
                        }
                    }
                }
            }
            $this->SendDebug(__FUNCTION__, 'RADIATOR:' . $sm);
            if ($sm) {
                $this->SendMessage(self::WT_VENTILE);
            }
        }
    }

    /**
     * SendMessage - if setuped. its send a message to indicate the problems
     *
     * @param string $type
     */
    private function SendMessage(string $type)
    {
        $this->SendDebug(__FUNCTION__, 'TYPE: ' . $type);
        $dashboard = $this->ReadPropertyInteger('DashboardMessage');
        $notify = $this->ReadPropertyInteger('NotificationMessage');
        $this->SendDebug(__FUNCTION__, 'DASHBOARD: ' . $dashboard . ', NOTIFY: ' . $notify);
        // Check output
        if (!$dashboard && !$notify) {
            // nothing to do
            return;
        }
        $lifetime = $this->ReadPropertyInteger('DashboardLifetime');
        // text formates
        $cooling = $this->ReadPropertyString('TextCooldown');
        $heating = $this->ReadPropertyString('TextHeatup');
        $ventile = $this->ReadPropertyString('TextValvestate');

        // visu id & message script
        $visu = $this->ReadPropertyInteger('InstanceVisu');
        $title = $this->ReadPropertyString('TitleMessage');
        $script = $this->ReadPropertyInteger('ScriptMessage');
        // specifier
        $value = [];
        $value['ROOM'] = $this->ReadPropertyString('RoomName');
        $value['TYPE'] = (($type == self::WT_VENTILE) ? $this->Translate('Valve state') : (($type == self::WT_COOLING) ? $this->Translate('Cool down') : $this->Translate('Heat up')));
        $value['DATE'] = (date('d.m.Y', time()));
        $value['TIME'] = (date('H:i:s', time()));
        $format = (($type == self::WT_VENTILE) ? $ventile : (($type == self::WT_COOLING) ? $cooling : $heating));
        $this->SendDebug(__FUNCTION__, 'FORMAT: ' . $format);
        // build message
        $text = $this->FormatMessage($value, $format);
        $image = (($type == self::WT_VENTILE) ? 'circle-exclamation' : (($type == self::WT_COOLING) ? 'temperature-low' : 'temperature-high'));
        $code = 1;
        $time = $lifetime * 60;
        // debug
        $this->SendDebug(__FUNCTION__, 'IMAGE:' . $image . ', TEXT: ' . $text . ', TIME:' . $time);
        // send notify?
        if ($notify && ($visu >= self::IPS_MIN_ID)) {
            if ($this->IsWebFrontVisuInstance($visu)) {
                WFC_PushNotification($visu, $title, $text, $image, 0);
            }
            if ($this->IsTileVisuInstance($visu)) {
                VISU_PostNotificationEx($visu, $title, $text, $image, 'buzzer', 0);
            }
        }
        // send message?
        if ($dashboard && $script >= self::IPS_MIN_ID) {
            if ($time > 0) {
                IPS_RunScriptWaitEx($script, ['action' => 'add', 'text' => $text, 'expires' => time() + $time, 'removable' => true, 'type' => $code, 'image' => $image]);
            } else {
                IPS_RunScriptWaitEx($script, ['action' => 'add', 'text' => $text, 'removable' => true, 'type' => $code, 'image' => $image]);
            }
        }
    }

    /**
     * Format a given array to a string.
     *
     * @param array $value Weather warning data
     * @param string $format Format string
     */
    private function FormatMessage(array $value, $format)
    {
        $output = str_replace('%R', $value['ROOM'], $format);
        $output = str_replace('%M', $value['TYPE'], $output);
        $output = str_replace('%D', $value['DATE'], $output);
        $output = str_replace('%T', $value['TIME'], $output);
        return $output;
    }

    /**
     * Weekly Schedule event
     *
     * @param integer $value Action value (ON=2, OFF=1)
     */
    private function ScheduleControl(int $value)
    {
        $schedule = $this->ReadPropertyInteger('TestSchedule');
        $this->SendDebug(__FUNCTION__, 'Value: ' . $value . ',Schedule: ' . $schedule);
        if ($schedule == 0) {
            // nothing todo
            return;
        }
        // Is Activate OFF
        if ($value == self::WT_SCHEDULE_CONTROL_OFF) {
            $this->SendDebug(__FUNCTION__, 'OFF: Deactivate schedule timer!');
            // Reset Timer
            $this->SetTimerInterval('TimerControl', 0);
            return;
        }
        // Schedule is aktiv?
        $interval = $this->ReadPropertyInteger('TestInterval');
        $this->SendDebug(__FUNCTION__, 'ON: Interval is:' . $interval);
        if ($interval > 0) {
            $this->SendDebug(__FUNCTION__, 'ON: Activate schedule timer:' . $interval);
            $this->SetTimerInterval('TimerControl', 60 * 1000 * $interval);
            // Start with Update and than wait for Timer
            $this->Control();
        }
    }

    /**
     * Create week schedule for snapshots
     *
     */
    private function CreateSchedule()
    {
        $eid = $this->CreateWeeklySchedule($this->InstanceID, $this->Translate('Schedule control'), self::WT_SCHEDULE_CONTROL_IDENT, self::WT_SCHEDULE_CONTROL_SWITCH, -1);
        if ($eid !== false) {
            $this->UpdateFormField('TestSchedule', 'value', $eid);
        }
    }
}
