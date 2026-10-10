<?php

declare(strict_types=1);

/** Generell funktions */
require_once __DIR__ . '/../libs/_traits.php';

/** Namespaced traits */
use Wilkware\WallThermostat\DebugHelper;
use Wilkware\WallThermostat\EventHelper;
use Wilkware\WallThermostat\VariableHelper;
use Wilkware\WallThermostat\VersionHelper;

/**
 * CLASS WallThermostat
 */
class WallThermostat extends IPSModuleStrict
{
    // -------------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------------

    use DebugHelper;
    use EventHelper;
    use VariableHelper;
    use VersionHelper;

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    /** @var string Active Profile variable name */
    public const WT_PROFILE = 'ACTIVE_PROFILE';

    /** @var string Set Temperature Mode variable name */
    public const WT_MODE = 'SET_POINT_MODE';

    /** @var string Actual Temperature variable name */
    public const WT_ACTUAL = 'ACTUAL_TEMPERATURE';

    /** @var string Set Point Temperature variable name */
    public const WT_TEMP = 'SET_POINT_TEMPERATURE';

    /** @var string Valve State variable name */
    public const WT_STATE = 'VALVE_STATE';

    /** @var string Cooling message ident constant */
    public const WT_COOLING = 'COOLING';

    /** @var string Heating message ident constant */
    public const WT_HEATING = 'HEATING';

    /** @var string Ventile message ident constant */
    public const WT_VENTILE = 'VENTILE';

    /** @var int Min instance ID constant */
    private const IPS_MIN_ID = 10000;

    /** @var int Schedule recording constant OFF */
    private const WT_SCHEDULE_CONTROL_OFF = 1;

    /** @var int Schedule recording constant ON */
    private const WT_SCHEDULE_CONTROL_ON = 2;

    /** @var string Schedule recording constant IDENT */
    private const WT_SCHEDULE_CONTROL_IDENT = 'circuit_control';

    /**
     * @var array<int,array{0:string,1:int,2:string}> Schedule recording constant ACTION (Switch)
     */
    private const WT_SCHEDULE_CONTROL_SWITCH = [
        self::WT_SCHEDULE_CONTROL_OFF => ['Inaktive', 0xFF0000, "IPS_RequestAction(\$_IPS['TARGET'], 'schedule_control', \$_IPS['ACTION']);"],
        self::WT_SCHEDULE_CONTROL_ON  => ['Aktive', 0x00FF00, "IPS_RequestAction(\$_IPS['TARGET'], 'schedule_control', \$_IPS['ACTION']);"],
    ];

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     *
     * @return void
     */
    public function Create(): void
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

        $this->RegisterPropertyBoolean('CheckCircuit', false);
        $this->RegisterPropertyInteger('CircuitVariable', 1);
        $this->RegisterPropertyInteger('CircuitType', 3);
        $this->RegisterPropertyString('CircuitValue', '');

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
     * The function is not called when exiting Symcon.
     *
     * @return void
     */
    public function Destroy(): void
    {
        // Never delete this line!
        parent::Destroy();
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     *
     * @return void
     */
    public function ApplyChanges(): void
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
                $this->LogDebug(__FUNCTION__, 'Instance does not exist - Variable: ' . $thermostat);
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
                    $this->LogDebug(__FUNCTION__, 'Instance does not exist - Variable: ' . $radiator);
                    $this->SetStatus(104);
                    return;
                }
            }
        }
        $heating = $this->ReadPropertyInteger('CircuitVariable');
        if ($heating >= self::IPS_MIN_ID) {
            if (IPS_VariableExists($heating)) {
                $this->RegisterReference($heating);
            } else {
                $this->LogDebug(__FUNCTION__, 'Variable does not exist - Variable: ' . $heating);
                $this->SetStatus(104);
                return;
            }
        }
        $visu = $this->ReadPropertyInteger('InstanceVisu');
        if ($visu >= self::IPS_MIN_ID) {
            if (IPS_InstanceExists($visu)) {
                $this->RegisterReference($visu);
            } else {
                $this->LogDebug(__FUNCTION__, 'Instance does not exist - Variable: ' . $visu);
                $this->SetStatus(104);
                return;
            }
        }
        $schedule = $this->ReadPropertyInteger('TestSchedule');
        if ($schedule >= self::IPS_MIN_ID) {
            if (IPS_EventExists($schedule)) {
                $this->RegisterReference($schedule);
            } else {
                $this->LogDebug(__FUNCTION__, 'Event does not exist - Variable: ' . $schedule);
                $this->SetStatus(104);
                return;
            }
        }
        $script = $this->ReadPropertyInteger('ScriptMessage');
        if ($script >= self::IPS_MIN_ID) {
            if (IPS_ScriptExists($script)) {
                $this->RegisterReference($script);
            } else {
                $this->LogDebug(__FUNCTION__, 'Script does not exist - Variable: ' . $script);
                $this->SetStatus(104);
                return;
            }
        }

        // Register message update for variables
        $vid = @IPS_GetObjectIDByName(self::WT_PROFILE, $thermostat);
        if ($vid !== false) {
            $this->RegisterMessage($vid, VM_UPDATE);
        }
        $vid = @IPS_GetObjectIDByName(self::WT_MODE, $thermostat);
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
                    $this->LogDebug(__FUNCTION__, $wsi);
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
     * The content of the function can be overwritten in order to carry out own reactions to certain messages.
     * The function is only called for registered MessageIDs/SenderIDs combinations.
     *
     * data[0] = new value
     * data[1] = value changed?
     * data[2] = old value
     * data[3] = timestamp.
     *
     * @param int   $timestamp Continuous counter timestamp
     * @param int   $sender    Sender ID
     * @param int   $message   ID of the message
     * @param array{0:mixed,1:bool,2:mixed,3:int} $data Data of the message
     * @return void
     */
    public function MessageSink(int $timestamp, int $sender, int $message, array $data): void
    {
        //$this->LogDebug(__FUNCTION__, 'SenderId: '. $senderID . ' Data: ' . print_r($data, true), 0);
        $profile = 1;
        $mode = 1;
        $thermostat = $this->ReadPropertyInteger('Thermostat');
        if (IPS_InstanceExists($thermostat)) {
            $vid = @IPS_GetObjectIDByName(self::WT_PROFILE, $thermostat);
            if ($vid !== false) {
                $profile = $vid;
            }
            $vid = @IPS_GetObjectIDByName(self::WT_MODE, $thermostat);
            if ($vid !== false) {
                $mode = $vid;
            }
        }
        switch ($message) {
            case VM_UPDATE:
                if ($sender == $profile) {
                    // state changes ?
                    if ($data[1] == true) {
                        $this->LogDebug(__FUNCTION__, 'Profile switched to <' . $data[0] . '>');
                        $this->Profile($data[0]);
                    }
                }
                if ($sender == $mode) {
                    // state changes ?
                    if ($data[1] == true) {
                        $this->LogDebug(__FUNCTION__, 'Mode switched to <' . $data[0] . '>');
                        $this->Mode($data[0]);
                    }
                }
                break;

        }
    }

    /**
     * Is called when, for example, a button is clicked in the visualization.
     *
     * @param string $ident Ident of the variable
     * @param mixed $value The value to be set
     *
     * @return void
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        // Debug output
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $value);
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
     * @param int $value Value of the new selected profile.
     *
     * @return void
     */
    private function Profile($value): void
    {
        // Safty check
        if ($value < 1 || $value > 3) {
            return;
        }
        // Switch all radiators
        for ($i = 1; $i <= 8; $i++) {
            $radiator = $this->ReadPropertyInteger('Radiator' . $i);
            $this->LogDebug(__FUNCTION__, 'Radiator' . $i . ': ID => ' . $radiator);
            if (IPS_InstanceExists($radiator)) {
                $ret = HM_WriteValueInteger($radiator, 'ACTIVE_PROFILE', $value);
            }
        }
    }

    /**
     * Mode - switch mode.
     *
     * @param int $value Value of the new selected mode.
     *
     * @return void
     */
    private function Mode($value): void
    {
        // Safty check
        if ($value < 0 || $value > 3) {
            return;
        }
        // Switch all radiators
        for ($i = 1; $i <= 8; $i++) {
            $radiator = $this->ReadPropertyInteger('Radiator' . $i);
            $this->LogDebug(__FUNCTION__, 'Radiator' . $i . ': ID => ' . $radiator);
            if (IPS_InstanceExists($radiator)) {
                $ret = HM_WriteValueInteger($radiator, 'CONTROL_MODE', $value);
            }
        }
    }

    /**
     * Control
     *
     * @return void
     */
    private function Control(): void
    {
        // What to check?
        $cooling = $this->ReadPropertyBoolean('CheckCooling');
        $heating = $this->ReadPropertyBoolean('CheckHeating');
        $ventile = $this->ReadPropertyBoolean('CheckVentile');
        $circuit = $this->ReadPropertyBoolean('CheckCircuit');

        $check = true;
        if ($circuit) {
            $this->LogDebug(__FUNCTION__, 'CIRCUIT: ' . $circuit);
            $vid = $this->ReadPropertyInteger('CircuitVariable');
            if (IPS_VariableExists($vid)) {
                $state = GetValue($vid);
                $this->LogDebug(__FUNCTION__, 'STATE: ' . $state);
                $type = $this->ReadPropertyInteger('CircuitType');
                $value = $this->ReadPropertyString('CircuitValue');
                if ($type == 0) {
                    $check = ($state == boolval($value));
                } elseif ($type == 1) {
                    $check = ($state == intval($value));
                } elseif ($type == 2) {
                    $check = ($state == floatval($value));
                } else {
                    $check = ($state == $value);
                }
                $this->LogDebug(__FUNCTION__, 'CHECK: ' . var_export($check, true));
            }
        }
        // Actual & Shall temperature
        $at = 100.0;
        $st = 100.0;
        // for both the same
        if (($cooling || $heating) && $check) {
            $thermostat = $this->ReadPropertyInteger('Thermostat');
            $vid = @IPS_GetObjectIDByName(self::WT_ACTUAL, $thermostat);
            if ($vid !== false) {
                $at = GetValueFloat($vid);
            }
            $vid = @IPS_GetObjectIDByName(self::WT_TEMP, $thermostat);
            if ($vid !== false) {
                $st = GetValueFloat($vid);
            }
        }
        $this->LogDebug(__FUNCTION__, 'ACTUAL: ' . $at . ', TARGET: ' . $st);
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
                    $vid = @IPS_GetObjectIDByName(self::WT_STATE, $radiator);
                    if ($vid !== false) {
                        $vs = GetValueInteger($vid);
                    }
                    $this->LogDebug(__FUNCTION__, 'RADIATOR_' . $i . ': ' . $vs . ', SV: ' . $sv);
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
            $this->LogDebug(__FUNCTION__, 'RADIATOR:' . $sm);
            if ($sm) {
                $this->SendMessage(self::WT_VENTILE);
            }
        }
    }

    /**
     * SendMessage - if setuped. its send a message to indicate the problems
     *
     * @param string $type
     *
     * @return void
     */
    private function SendMessage(string $type): void
    {
        $this->LogDebug(__FUNCTION__, 'TYPE: ' . $type);
        $dashboard = $this->ReadPropertyInteger('DashboardMessage');
        $notify = $this->ReadPropertyInteger('NotificationMessage');
        $this->LogDebug(__FUNCTION__, 'DASHBOARD: ' . $dashboard . ', NOTIFY: ' . $notify);
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
        $this->LogDebug(__FUNCTION__, 'FORMAT: ' . $format);
        // build message
        $text = $this->FormatMessage($value, $format);
        $image = (($type == self::WT_VENTILE) ? 'circle-exclamation' : (($type == self::WT_COOLING) ? 'temperature-low' : 'temperature-high'));
        $code = 1;
        $time = $lifetime * 60;
        // debug
        $this->LogDebug(__FUNCTION__, 'IMAGE:' . $image . ', TEXT: ' . $text . ', TIME:' . $time);
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
     * @param array<string,string> $value Heating inspection data
     * @param string $format Format string
     *
     * @return string Formatted string
     */
    private function FormatMessage(array $value, string $format): string
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
     *
     * @return void
     */
    private function ScheduleControl(int $value): void
    {
        $schedule = $this->ReadPropertyInteger('TestSchedule');
        $this->LogDebug(__FUNCTION__, 'Value: ' . $value . ',Schedule: ' . $schedule);
        if ($schedule == 0) {
            // nothing todo
            return;
        }
        // Is Activate OFF
        if ($value == self::WT_SCHEDULE_CONTROL_OFF) {
            $this->LogDebug(__FUNCTION__, 'OFF: Deactivate schedule timer!');
            // Reset Timer
            $this->SetTimerInterval('TimerControl', 0);
            return;
        }
        // Schedule is aktiv?
        $interval = $this->ReadPropertyInteger('TestInterval');
        $this->LogDebug(__FUNCTION__, 'ON: Interval is:' . $interval);
        if ($interval > 0) {
            $this->LogDebug(__FUNCTION__, 'ON: Activate schedule timer:' . $interval);
            $this->SetTimerInterval('TimerControl', 60 * 1000 * $interval);
            // Start with Update and than wait for Timer
            $this->Control();
        }
    }

    /**
     * Create week schedule for snapshots
     *
     * @return void
     */
    private function CreateSchedule(): void
    {
        $eid = $this->CreateWeeklySchedule($this->InstanceID, $this->Translate('Schedule control'), self::WT_SCHEDULE_CONTROL_IDENT, self::WT_SCHEDULE_CONTROL_SWITCH, -1);
        if ($eid >= self::IPS_MIN_ID) {
            $this->UpdateFormField('TestSchedule', 'value', $eid);
        }
    }
}
