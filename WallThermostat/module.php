<?php

declare(strict_types=1);

/** General functions */
require_once __DIR__ . '/../libs/_traits.php';

/** Namespaced traits */
use Wilkware\WallThermostat\DebugHelper;
use Wilkware\WallThermostat\EventHelper;
use Wilkware\WallThermostat\FormHelper;
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
    use FormHelper;
    use VariableHelper;
    use VersionHelper;

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    /** @var string Active profile variable ident (HomeMatic) */
    private const WT_PROFILE = 'ACTIVE_PROFILE';

    /** @var string Set point mode variable ident (HomeMatic) */
    private const WT_MODE = 'SET_POINT_MODE';

    /** @var string Control mode parameter (HomeMatic, write-only, no variable) */
    private const WT_CONTROL = 'CONTROL_MODE';

    /** @var string Actual temperature variable ident (HomeMatic) */
    private const WT_ACTUAL = 'ACTUAL_TEMPERATURE';

    /** @var string Set point temperature variable ident (HomeMatic) */
    private const WT_TEMP = 'SET_POINT_TEMPERATURE';

    /** @var string Valve state variable ident (HomeMatic) */
    private const WT_STATE = 'VALVE_STATE';

    /** @var string Cooling message type */
    private const WT_COOLING = 'COOLING';

    /** @var string Heating message type */
    private const WT_HEATING = 'HEATING';

    /** @var string Valve message type */
    private const WT_VENTILE = 'VENTILE';

    /** @var array<string,string> Message trigger names (untranslated) */
    private const WT_TRIGGERS = [
        self::WT_COOLING => 'Cool down',
        self::WT_HEATING => 'Heat up',
        self::WT_VENTILE => 'Valve state',
    ];

    /** @var int Number of supported radiators */
    private const WT_RADIATORS = 8;

    /** @var int Valve state "ready" (HomeMatic VALVE_STATE) */
    private const WT_VALVE_READY = 4;

    /** @var array<int> Valve states which signal an error (HomeMatic VALVE_STATE) */
    private const WT_VALVE_ERRORS = [5, 6, 7];

    /** @var int Min instance ID constant */
    private const IPS_MIN_ID = 10000;

    /** @var int Status code: thermostat or radiator instance invalid */
    private const STATUS_DEVICE_INVALID = 201;

    /** @var int Status code: heating circuit variable invalid */
    private const STATUS_CIRCUIT_INVALID = 202;

    /** @var int Status code: schedule, visualization or message script invalid */
    private const STATUS_OBJECT_INVALID = 203;

    /** @var string Default sound of push notifications */
    private const NOTIFICATION_SOUND = 'buzzer';

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
        self::WT_SCHEDULE_CONTROL_OFF => ['Inactive', 0xFF0000, "IPS_RequestAction(\$_IPS['TARGET'], 'schedule_control', \$_IPS['ACTION']);"],
        self::WT_SCHEDULE_CONTROL_ON  => ['Active', 0x00FF00, "IPS_RequestAction(\$_IPS['TARGET'], 'schedule_control', \$_IPS['ACTION']);"],
    ];

    /** @var array<string,mixed> Switch presentation (profile sync) */
    private const WT_PRESENTATION_PROFILE = [
        'ICON_TRUE'    => 'calendar-days',
        'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
    ];

    /** @var array<string,mixed> Switch presentation (mode sync) */
    private const WT_PRESENTATION_MODE = [
        'ICON_TRUE'    => 'sliders',
        'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
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
        for ($i = 1; $i <= self::WT_RADIATORS; $i++) {
            $this->RegisterPropertyInteger('Radiator' . $i, 1);
        }

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
        $this->RegisterPropertyString('NotificationSound', self::NOTIFICATION_SOUND);
        $this->RegisterPropertyInteger('ScriptMessage', 0);
        $this->RegisterPropertyString('MessageKey', '');

        // Settings
        $this->RegisterPropertyBoolean('SyncProfile', false);
        $this->RegisterPropertyBoolean('SyncMode', false);

        // Register update timer
        $this->RegisterTimer('TimerControl', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "control", "");');
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Reset timer and alarm states, they are set again below
        $this->SetTimerInterval('TimerControl', 0);
        $this->SetBuffer('Alarms', '');

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
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Switches (Settings)
        $this->MaintainSyncVariable('SyncProfile', 'Profile', self::WT_PRESENTATION_PROFILE, 1);
        $this->MaintainSyncVariable('SyncMode', 'Mode', self::WT_PRESENTATION_MODE, 2);

        // Access to other objects only with running kernel
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        // Thermostat & radiators are required for everything
        $devices = [$this->ReadPropertyInteger('Thermostat')];
        for ($i = 1; $i <= self::WT_RADIATORS; $i++) {
            $devices[] = $this->ReadPropertyInteger('Radiator' . $i);
        }
        foreach ($devices as $device) {
            if ($device < self::IPS_MIN_ID) {
                continue;
            }
            if (!IPS_InstanceExists($device)) {
                $this->LogDebug(__FUNCTION__, 'Instance does not exist: ' . $device);
                $this->SetStatus(self::STATUS_DEVICE_INVALID);
                return;
            }
            $this->RegisterReference($device);
        }

        // Register message update for thermostat variables
        $thermostat = $devices[0];
        if ($thermostat >= self::IPS_MIN_ID) {
            foreach ([self::WT_PROFILE, self::WT_MODE] as $ident) {
                $vid = $this->GetDeviceVariable($thermostat, $ident);
                if ($vid !== 0) {
                    $this->RegisterMessage($vid, VM_UPDATE);
                }
            }
        }

        $status = 102;

        // Optional objects, an invalid one only disables the corresponding function
        $circuitVar = $this->ReadPropertyInteger('CircuitVariable');
        if ($circuitVar >= self::IPS_MIN_ID) {
            if (IPS_VariableExists($circuitVar)) {
                $this->RegisterReference($circuitVar);
            } else {
                $this->LogDebug(__FUNCTION__, 'Variable does not exist: ' . $circuitVar);
                $status = self::STATUS_CIRCUIT_INVALID;
            }
        }
        $visu = $this->ReadPropertyInteger('InstanceVisu');
        if ($visu >= self::IPS_MIN_ID) {
            if (IPS_InstanceExists($visu)) {
                $this->RegisterReference($visu);
            } else {
                $this->LogDebug(__FUNCTION__, 'Instance does not exist: ' . $visu);
                $status = self::STATUS_OBJECT_INVALID;
            }
        }
        $script = $this->ReadPropertyInteger('ScriptMessage');
        if ($script >= self::IPS_MIN_ID) {
            if (IPS_ScriptExists($script)) {
                $this->RegisterReference($script);
            } else {
                $this->LogDebug(__FUNCTION__, 'Script does not exist: ' . $script);
                $status = self::STATUS_OBJECT_INVALID;
            }
        }
        $schedule = $this->ReadPropertyInteger('TestSchedule');
        $scheduleValid = true;
        if ($schedule >= self::IPS_MIN_ID) {
            if (IPS_EventExists($schedule)) {
                $this->RegisterReference($schedule);
            } else {
                $this->LogDebug(__FUNCTION__, 'Event does not exist: ' . $schedule);
                $status = self::STATUS_OBJECT_INVALID;
                $scheduleValid = false;
            }
        }

        // Periodic inspection
        $interval = $this->ReadPropertyInteger('TestInterval');
        if ($this->IsInspectionActive() && ($interval > 0) && $scheduleValid) {
            if ($schedule < self::IPS_MIN_ID) {
                $this->SetTimerInterval('TimerControl', 60 * 1000 * $interval);
            } else {
                $wsi = $this->GetWeeklyScheduleInfo($schedule, time(), true);
                $this->LogDebug(__FUNCTION__, $wsi);
                if ($wsi['ActionID'] == self::WT_SCHEDULE_CONTROL_ON) {
                    $this->SetTimerInterval('TimerControl', 60 * 1000 * $interval);
                }
            }
        }

        $this->SetStatus($status);
    }

    /**
     * The content can be overwritten in order to transfer a self-created configuration page.
     * This way, content can be generated dynamically.
     * In this case, the "form.json" on the file system is completely ignored.
     *
     * @return string Content of the configuration page.
     */
    public function GetConfigurationForm(): string
    {
        // Get form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        // Heating circuit fields only with active check
        $circuit = $this->ReadPropertyBoolean('CheckCircuit');
        foreach (['CircuitVariable', 'CircuitType', 'CircuitValue'] as $name) {
            $this->ModifyFormElement($form['elements'], $name, function (array &$element) use ($circuit): void
            {
                $element['enabled'] = $circuit;
            });
        }

        // Extract version
        $ins = IPS_GetInstance($this->InstanceID);
        $mod = IPS_GetModule($ins['ModuleInfo']['ModuleID']);
        $lib = IPS_GetLibrary($mod['LibraryID']);
        $version = sprintf('v%s.%d', $lib['Version'], $lib['Build']);
        $this->ModifyFormElement($form['actions'], 'Version', function (array &$element) use ($version): void
        {
            $element['caption'] = $version;
        });

        return json_encode($form);
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
        switch ($message) {
            case IPS_KERNELSTARTED:
                $this->ApplyChanges();
                break;
            case VM_UPDATE:
                if ($data[1] !== true) {
                    break;
                }
                $ident = IPS_GetObject($sender)['ObjectIdent'];
                if ($ident === self::WT_PROFILE) {
                    $this->LogDebug(__FUNCTION__, 'Profile switched to <' . $data[0] . '>');
                    $this->Profile((int) $data[0]);
                } elseif ($ident === self::WT_MODE) {
                    $this->LogDebug(__FUNCTION__, 'Mode switched to <' . $data[0] . '>');
                    $this->Mode((int) $data[0]);
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
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $this->Stringify($value));
        switch ($ident) {
            case 'SyncProfile':
            case 'SyncMode':
                $this->SetValueBoolean($ident, (bool) $value);
                break;
            case 'create_schedule':
                $this->CreateSchedule();
                break;
            case 'schedule_control':
                $this->ScheduleControl((int) $value);
                break;
            case 'control':
                $this->Control();
                break;
            case 'control_test':
                $this->Control(true);
                break;
            case 'circuit_check':
                foreach (['CircuitVariable', 'CircuitType', 'CircuitValue'] as $name) {
                    $this->UpdateFormField($name, 'enabled', (bool) $value);
                }
                break;
            default:
                break;
        }
    }

    /**
     * Creates or removes a sync switch variable.
     * A new variable is switched on, the radiators are synchronized by default.
     *
     * @param string              $ident        Ident (and property name) of the switch
     * @param string              $name         Untranslated name of the variable
     * @param array<string,mixed> $presentation Presentation of the variable
     * @param int                 $position     Position of the variable
     *
     * @return void
     */
    private function MaintainSyncVariable(string $ident, string $name, array $presentation, int $position): void
    {
        $keep = $this->ReadPropertyBoolean($ident);
        $exists = IPS_VariableExists((int) @$this->GetIDForIdent($ident));
        $this->MaintainVariable($ident, $this->Translate($name), VARIABLETYPE_BOOLEAN, $presentation, $position, $keep);
        if (!$keep) {
            return;
        }
        if (!$exists) {
            $this->SetValueBoolean($ident, true);
        }
        $this->EnableAction($ident);
    }

    /**
     * Checks whether the synchronization is enabled (no switch = always enabled).
     *
     * @param string $ident Ident of the sync switch
     *
     * @return bool True if enabled, otherwise false.
     */
    private function IsSyncEnabled(string $ident): bool
    {
        $vid = (int) @$this->GetIDForIdent($ident);
        if (!IPS_VariableExists($vid)) {
            return true;
        }
        return GetValueBoolean($vid);
    }

    /**
     * Checks whether at least one periodic inspection is enabled.
     *
     * @return bool True if at least one inspection is enabled.
     */
    private function IsInspectionActive(): bool
    {
        return $this->ReadPropertyBoolean('CheckCooling')
            || $this->ReadPropertyBoolean('CheckHeating')
            || $this->ReadPropertyBoolean('CheckVentile');
    }

    /**
     * Returns the ID of a variable below a device instance.
     *
     * @param int    $instance Instance ID of the device
     * @param string $ident    Ident of the variable
     *
     * @return int Variable ID or 0 if not found.
     */
    private function GetDeviceVariable(int $instance, string $ident): int
    {
        if (!IPS_InstanceExists($instance)) {
            return 0;
        }
        $vid = @IPS_GetObjectIDByIdent($ident, $instance);
        return ($vid !== false) ? $vid : 0;
    }

    /**
     * Profile - switch profile.
     *
     * @param int $value Value of the new selected profile.
     *
     * @return void
     */
    private function Profile(int $value): void
    {
        if ($value < 1 || $value > 3) {
            return;
        }
        if (!$this->IsSyncEnabled('SyncProfile')) {
            $this->LogDebug(__FUNCTION__, 'Profile sync is disabled!');
            return;
        }
        $this->SyncRadiators(self::WT_PROFILE, $value);
    }

    /**
     * Mode - switch mode.
     *
     * @param int $value Value of the new selected mode.
     *
     * @return void
     */
    private function Mode(int $value): void
    {
        if ($value < 0 || $value > 3) {
            return;
        }
        if (!$this->IsSyncEnabled('SyncMode')) {
            $this->LogDebug(__FUNCTION__, 'Mode sync is disabled!');
            return;
        }
        if (!function_exists('HM_WriteValueInteger')) {
            $this->LogMessage('HomeMatic module not available, mode cannot be synchronized!', KL_WARNING);
            return;
        }
        $this->SyncRadiators(self::WT_MODE, $value);
    }

    /**
     * Switches the given variable of all radiators to the new value.
     *
     * @param string $ident Ident of the radiator variable
     * @param int    $value New value
     *
     * @return void
     */
    private function SyncRadiators(string $ident, int $value): void
    {
        for ($i = 1; $i <= self::WT_RADIATORS; $i++) {
            $radiator = $this->ReadPropertyInteger('Radiator' . $i);
            if ($radiator < self::IPS_MIN_ID) {
                continue;
            }
            $vid = $this->GetDeviceVariable($radiator, $ident);
            $this->LogDebug(__FUNCTION__, 'Radiator' . $i . ': ' . $radiator . ', ' . $ident . ': ' . $vid);
            if ($vid === 0) {
                $this->LogMessage(sprintf('Radiator %d: variable %s not found!', $radiator, $ident), KL_WARNING);
                continue;
            }
            if (GetValue($vid) === $value) {
                continue;
            }
            // SET_POINT_MODE is read-only, the mode can only be changed via the write-only parameter CONTROL_MODE
            if ($ident === self::WT_MODE) {
                $ok = @HM_WriteValueInteger($radiator, self::WT_CONTROL, $value);
            } else {
                $ok = @RequestAction($vid, $value);
            }
            if (!$ok) {
                $this->LogMessage(sprintf('Radiator %d: switching %s to %d failed!', $radiator, $ident, $value), KL_WARNING);
            }
        }
    }

    /**
     * Checks whether the heating circuit allows the temperature inspection.
     *
     * @return bool True if the inspection may be carried out.
     */
    private function IsCircuitActive(): bool
    {
        if (!$this->ReadPropertyBoolean('CheckCircuit')) {
            return true;
        }
        $vid = $this->ReadPropertyInteger('CircuitVariable');
        if (!IPS_VariableExists($vid)) {
            return true;
        }
        $state = GetValue($vid);
        $value = $this->ReadPropertyString('CircuitValue');
        $check = match ($this->ReadPropertyInteger('CircuitType')) {
            0       => (bool) $state === filter_var($value, FILTER_VALIDATE_BOOLEAN),
            1       => (int) $state === (int) $value,
            2       => (float) $state === (float) $value,
            default => (string) $state === $value,
        };
        $this->LogDebug(__FUNCTION__, 'STATE: ' . $this->Stringify($state) . ', VALUE: ' . $value . ', CHECK: ' . $this->Stringify($check));
        return $check;
    }

    /**
     * Control - periodic inspection.
     *
     * @param bool $report Show the result in a popup of the configuration form (test function)
     *
     * @return void
     */
    private function Control(bool $report = false): void
    {
        $cooling = $this->ReadPropertyBoolean('CheckCooling');
        $heating = $this->ReadPropertyBoolean('CheckHeating');
        $ventile = $this->ReadPropertyBoolean('CheckVentile');

        if ($report && !$this->IsInspectionActive()) {
            $this->EchoMessage('No inspection is activated!');
            return;
        }

        $alarms = [self::WT_COOLING => false, self::WT_HEATING => false, self::WT_VENTILE => false];
        $notes = [];

        // Temperature (cool down & heat up)
        $circuit = ($cooling || $heating) ? $this->IsCircuitActive() : true;
        if (!$circuit) {
            $notes[] = $this->Translate('Temperature not checked (heating circuit)');
        }
        if (($cooling || $heating) && $circuit) {
            $thermostat = $this->ReadPropertyInteger('Thermostat');
            $vat = $this->GetDeviceVariable($thermostat, self::WT_ACTUAL);
            $vst = $this->GetDeviceVariable($thermostat, self::WT_TEMP);
            if (($vat !== 0) && ($vst !== 0)) {
                $at = GetValueFloat($vat);
                $st = GetValueFloat($vst);
                $this->LogDebug(__FUNCTION__, 'ACTUAL: ' . $at . ', TARGET: ' . $st);
                $alarms[self::WT_COOLING] = $cooling && ($at < $st + $this->ReadPropertyFloat('DegreeCooling'));
                $alarms[self::WT_HEATING] = $heating && ($at > $st + $this->ReadPropertyFloat('DegreeHeating'));
            } else {
                $this->LogDebug(__FUNCTION__, 'Temperature variables of thermostat not found!');
                $notes[] = $this->Translate('Temperature variables of the thermostat not found');
            }
        }

        // Valve state
        if ($ventile) {
            $sv = $this->ReadPropertyInteger('StateVentile');
            for ($i = 1; $i <= self::WT_RADIATORS; $i++) {
                $radiator = $this->ReadPropertyInteger('Radiator' . $i);
                if ($radiator < self::IPS_MIN_ID) {
                    continue;
                }
                $vid = $this->GetDeviceVariable($radiator, self::WT_STATE);
                $vs = ($vid !== 0) ? GetValueInteger($vid) : -1;
                $this->LogDebug(__FUNCTION__, 'RADIATOR_' . $i . ': ' . $vs . ', SV: ' . $sv);
                if ($sv == 0) {
                    $failed = ($vs !== self::WT_VALVE_READY);
                } else {
                    $failed = in_array($vs, self::WT_VALVE_ERRORS, true);
                }
                if ($failed) {
                    $alarms[self::WT_VENTILE] = true;
                }
            }
        }

        // Only report changes from OK to alarm
        $previous = json_decode($this->GetBuffer('Alarms'), true);
        if (!is_array($previous)) {
            $previous = [];
        }
        $this->LogDebug(__FUNCTION__, $alarms);
        $found = [];
        $new = 0;
        $sent = 0;
        foreach ($alarms as $type => $alarm) {
            if (!$alarm) {
                continue;
            }
            $found[] = $this->Translate(self::WT_TRIGGERS[$type]);
            if (!($previous[$type] ?? false)) {
                $new++;
                if ($this->SendMessage($type)) {
                    $sent++;
                }
            }
        }
        $this->SetBuffer('Alarms', json_encode($alarms));

        if (!$report) {
            return;
        }
        if (empty($found)) {
            $text = $this->Translate('Inspection completed, no problem found.');
        } else {
            $text = sprintf($this->Translate('Inspection completed, problem found: %s'), implode(', ', $found)) . "\n"
                . (($new === 0) ? $this->Translate('Problem already reported, no new message sent.')
                    : (($sent > 0) ? $this->Translate('Message has been sent.') : $this->Translate('No message sent (dashboard/notification not configured).')));
        }
        if (!empty($notes)) {
            $text .= "\n" . $this->Translate('Note:') . ' ' . implode(', ', $notes);
        }
        $this->EchoMessage($text);
    }

    /**
     * Show message via popup in the configuration form.
     *
     * @param string $caption Message (english, will be translated)
     *
     * @return void
     */
    private function EchoMessage(string $caption): void
    {
        $this->UpdateFormField('EchoMessage', 'caption', $this->Translate($caption));
        $this->UpdateFormField('EchoPopup', 'visible', true);
    }

    /**
     * SendMessage - if configured, sends a message to indicate the problem.
     *
     * @param string $type Message type (WT_COOLING, WT_HEATING, WT_VENTILE)
     *
     * @return bool True if at least one message was sent.
     */
    private function SendMessage(string $type): bool
    {
        $dashboard = $this->ReadPropertyInteger('DashboardMessage');
        $notify = $this->ReadPropertyInteger('NotificationMessage');
        $this->LogDebug(__FUNCTION__, 'TYPE: ' . $type . ', DASHBOARD: ' . $dashboard . ', NOTIFY: ' . $notify);
        if (!$dashboard && !$notify) {
            return false;
        }
        $sent = false;

        $texts = [
            self::WT_COOLING => ['TextCooldown', 'temperature-low'],
            self::WT_HEATING => ['TextHeatup', 'temperature-high'],
            self::WT_VENTILE => ['TextValvestate', 'circle-exclamation'],
        ];
        [$property, $image] = $texts[$type];

        $value = [
            'ROOM' => $this->ReadPropertyString('RoomName'),
            'TYPE' => $this->Translate(self::WT_TRIGGERS[$type]),
            'DATE' => date('d.m.Y'),
            'TIME' => date('H:i:s'),
        ];
        $text = $this->FormatMessage($value, $this->ReadPropertyString($property));
        $this->LogDebug(__FUNCTION__, 'IMAGE: ' . $image . ', TEXT: ' . $text);

        // Push notification
        $visu = $this->ReadPropertyInteger('InstanceVisu');
        if ($notify && ($visu >= self::IPS_MIN_ID)) {
            $title = $this->ReadPropertyString('TitleMessage');
            $sound = $this->ReadPropertyString('NotificationSound');
            if ($this->IsWebFrontVisuInstance($visu)) {
                WFC_PushNotification($visu, $title, $text, $sound, 0);
                $sent = true;
            }
            if ($this->IsTileVisuInstance($visu)) {
                VISU_PostNotificationEx($visu, $title, $text, $image, $sound, 0);
                $sent = true;
            }
        }

        // Dashboard message
        $script = $this->ReadPropertyInteger('ScriptMessage');
        if ($dashboard && IPS_ScriptExists($script)) {
            $params = ['action' => 'add', 'text' => $text, 'removable' => true, 'type' => 1, 'image' => $image];
            $lifetime = $this->ReadPropertyInteger('DashboardLifetime') * 60;
            if ($lifetime > 0) {
                $params['expires'] = time() + $lifetime;
            }
            // Older message scripts ignore the key, so it is only passed if configured
            $key = $this->ReadPropertyString('MessageKey');
            if ($key !== '') {
                $params['key'] = $key . '.' . strtolower($type);
            }
            IPS_RunScriptEx($script, $params);
            $sent = true;
        }
        return $sent;
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
        return str_replace(['%R', '%M', '%D', '%T'], [$value['ROOM'], $value['TYPE'], $value['DATE'], $value['TIME']], $format);
    }

    /**
     * Weekly Schedule event
     *
     * @param int $value Action value (ON=2, OFF=1)
     *
     * @return void
     */
    private function ScheduleControl(int $value): void
    {
        $schedule = $this->ReadPropertyInteger('TestSchedule');
        $this->LogDebug(__FUNCTION__, 'Value: ' . $value . ', Schedule: ' . $schedule);
        if ($schedule < self::IPS_MIN_ID) {
            return;
        }
        if ($value == self::WT_SCHEDULE_CONTROL_OFF) {
            $this->LogDebug(__FUNCTION__, 'OFF: Deactivate schedule timer!');
            $this->SetTimerInterval('TimerControl', 0);
            return;
        }
        $interval = $this->ReadPropertyInteger('TestInterval');
        if (($interval > 0) && $this->IsInspectionActive()) {
            $this->LogDebug(__FUNCTION__, 'ON: Activate schedule timer: ' . $interval);
            $this->SetTimerInterval('TimerControl', 60 * 1000 * $interval);
            // Start with check and then wait for timer
            $this->Control();
        }
    }

    /**
     * Create week schedule for the periodic inspection
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
