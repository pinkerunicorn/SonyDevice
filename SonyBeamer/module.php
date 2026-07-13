<?php

declare(strict_types=1);

class SonyBeamer extends IPSModuleStrict
{

    private $inputMap = [
        0 => 'hdmi1',
        1 => 'hdmi2',
        2 => 'video1',
        3 => 'component'
    ];

    private $pictureModeMap = [
        0 => 'dynamic',
        1 => 'standard',
        2 => 'brt_priority',
        3 => 'cinema_film_1',
        4 => 'cinema_film_2',
        5 => 'reference',
        6 => 'tv',
        7 => 'photo',
        8 => 'game',
        9 => 'bright_cinema',
        10 => 'bright_tv',
        11 => 'user'
    ];

    public function Create(): void{
        parent::Create();

        // Eigenschaften
        $this->RegisterPropertyInteger('UpdateInterval', 20); // Default to 20s to prevent 30s timeout

        // Timer fr Polling
        $this->RegisterTimer('UpdateTimer', 0, 'SONY_UpdateStatus($_IPS[\'TARGET\']);');

        // Puffer fr TCP-Fragmente
        $this->SetBuffer('DataBuffer', '');

        // Variablen registrieren
        $this->RegisterVariableBoolean('Power', '📺 Status', '', 10);
        $this->EnableAction('Power');

        // Alte String-Variablen entfernen, falls vorhanden
        $inputId = @$this->GetIDForIdent('Input');
        if ($inputId && IPS_GetVariable($inputId)['VariableType'] !== 1) { // 1 = Integer
            $this->UnregisterVariable('Input');
        }
        $picId = @$this->GetIDForIdent('PictureMode');
        if ($picId && IPS_GetVariable($picId)['VariableType'] !== 1) {
            $this->UnregisterVariable('PictureMode');
        }

        $this->RegisterVariableInteger('Input', '🔌 Eingang', 'Sony.Input', 20);
        $this->EnableAction('Input');

        $this->RegisterVariableInteger('PictureMode', '🖼️ Bildmodus', 'Sony.PictureMode', 30);
        $this->EnableAction('PictureMode');

        $this->RegisterVariableInteger('OperationTime', '⏱️ Betriebsstunden', '', 40);
        $this->RegisterVariableInteger('LightSourceTime', '💡 Lampenstunden', '', 50);
        $this->RegisterVariableString('Warning', '⚠️ Warnungen', '', 60);
    }

    public function ApplyChanges(): void{
        parent::ApplyChanges();

        $interval = $this->ReadPropertyInteger('UpdateInterval');
        if ($interval == 30) {
            // Empfehlung: Auf 20 Sekunden setzen, um Timeout zu verhindern.
            // Ändern des Intervalls via Code ist nicht erlaubt, aber wir setzen den Timer so.
            $interval = 20;
        }
        $this->SetTimerInterval('UpdateTimer', $interval * 1000);

        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Power'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
            'ICON'         => 'Power'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('OperationTime'), [
            'ICON' => 'Clock',
            'SUFFIX' => ' h'
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('LightSourceTime'), [
            'ICON' => 'Bulb',
            'SUFFIX' => ' h'
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Warning'), [
            'ICON' => 'Warning'
        ]);
        
        // Input Profil
        if (IPS_VariableProfileExists('Sony.Input') && IPS_GetVariableProfile('Sony.Input')['ProfileType'] !== 1) {
            IPS_DeleteVariableProfile('Sony.Input');
        }
        if (!IPS_VariableProfileExists('Sony.Input')) {
            IPS_CreateVariableProfile('Sony.Input', 1); // 1 = Integer
            IPS_SetVariableProfileAssociation('Sony.Input', 0, 'HDMI 1', '', -1);
            IPS_SetVariableProfileAssociation('Sony.Input', 1, 'HDMI 2', '', -1);
            IPS_SetVariableProfileAssociation('Sony.Input', 2, 'Video 1', '', -1);
            IPS_SetVariableProfileAssociation('Sony.Input', 3, 'Component', '', -1);
        }
        IPS_SetVariableCustomProfile($this->GetIDForIdent('Input'), 'Sony.Input');
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Input'), [
            'ICON' => 'Plug'
        ]);
        
        // Picture Mode Profil
        if (IPS_VariableProfileExists('Sony.PictureMode') && IPS_GetVariableProfile('Sony.PictureMode')['ProfileType'] !== 1) {
            IPS_DeleteVariableProfile('Sony.PictureMode');
        }
        if (!IPS_VariableProfileExists('Sony.PictureMode')) {
            IPS_CreateVariableProfile('Sony.PictureMode', 1); // 1 = Integer
            IPS_SetVariableProfileAssociation('Sony.PictureMode', 0, 'Dynamic', '', -1);
            IPS_SetVariableProfileAssociation('Sony.PictureMode', 1, 'Standard', '', -1);
            IPS_SetVariableProfileAssociation('Sony.PictureMode', 2, 'Brightness Priority', '', -1);
            IPS_SetVariableProfileAssociation('Sony.PictureMode', 3, 'Cinema Film 1', '', -1);
            IPS_SetVariableProfileAssociation('Sony.PictureMode', 4, 'Cinema Film 2', '', -1);
            IPS_SetVariableProfileAssociation('Sony.PictureMode', 5, 'Reference', '', -1);
            IPS_SetVariableProfileAssociation('Sony.PictureMode', 6, 'TV', '', -1);
            IPS_SetVariableProfileAssociation('Sony.PictureMode', 7, 'Photo', '', -1);
            IPS_SetVariableProfileAssociation('Sony.PictureMode', 8, 'Game', '', -1);
            IPS_SetVariableProfileAssociation('Sony.PictureMode', 9, 'Bright Cinema', '', -1);
            IPS_SetVariableProfileAssociation('Sony.PictureMode', 10, 'Bright TV', '', -1);
            IPS_SetVariableProfileAssociation('Sony.PictureMode', 11, 'User', '', -1);
        }
        IPS_SetVariableCustomProfile($this->GetIDForIdent('PictureMode'), 'Sony.PictureMode');
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('PictureMode'), [
            'ICON' => 'TV'
        ]);

        $this->UpdateVisibility($this->GetValue('Power'));
    }

    private function UpdateVisibility(bool $isVisible): void
    {
        $hidden = !$isVisible;
        $this->SetHiddenSafe('Input', $hidden);
        $this->SetHiddenSafe('PictureMode', $hidden);
        $this->SetHiddenSafe('OperationTime', $hidden);
        $this->SetHiddenSafe('LightSourceTime', $hidden);
        $this->SetHiddenSafe('Warning', $hidden);
    }

    private function SetHiddenSafe(string $ident, bool $hidden): void
    {
        $id = @$this->GetIDForIdent($ident);
        if ($id > 0) {
            IPS_SetHidden($id, $hidden);
        }
    }

    protected function Log(string $Message): void
    {
        IPS_LogMessage('SmartVillaKunterbunt', 'SonyBeamer: ' . $Message);
    }

    public function RequestAction(string $Ident, $Value): void{
        switch ($Ident) {
            case 'Power':
                if ($Value) {
                    $this->SendCommand("power \"on\"");
                    $this->Log("Einschaltbefehl gesendet.");
                } else {
                    $this->SendCommand("power \"off\"");
                    $this->Log("Ausschaltbefehl gesendet.");
                }
                break;
            case 'Input':
                if (isset($this->inputMap[$Value])) {
                    $cmdVal = $this->inputMap[$Value];
                    $this->SendCommand("input \"$cmdVal\"");
                    $this->Log("Eingang auf $cmdVal gesetzt.");
                }
                break;
            case 'PictureMode':
                if (isset($this->pictureModeMap[$Value])) {
                    $cmdVal = $this->pictureModeMap[$Value];
                    $this->SendCommand("picture_mode \"$cmdVal\"");
                    $this->Log("Bildmodus auf $cmdVal gesetzt.");
                }
                break;
            default:
                throw new Exception("Invalid Action");
        }
        
        // Kurz warten und dann Status frisch vom Gert abfragen
        IPS_Sleep(500);
        $this->UpdateStatus();
    }

    public function UpdateStatus(): void
    {
        if (!$this->HasActiveParent()) {
            $this->SendDebug("Log", "UpdateStatus abgebrochen: Kein aktives übergeordnetes Gateway gefunden!", 0);
            return;
        }

        $this->SendDebug("Log", "Sende Status-Abfragen an Beamer...", 0);
        $this->SendCommand("power_status ?");
        
        // Vermeide End-of-File Fehler, indem weitere Abfragen nur gesendet werden,
        // wenn der Beamer laut letztem Stand eingeschaltet ist. Im Standby reagiert
        // der Beamer empfindlich auf viele gleichzeitige Anfragen und bricht die Verbindung ab.
        if ($this->GetValue('Power')) {
            IPS_Sleep(100);
            $this->SendCommand("input ?");
            IPS_Sleep(100);
            $this->SendCommand("picture_mode ?");
            IPS_Sleep(100);
            $this->SendCommand("error ?");
            IPS_Sleep(100);
            $this->SendCommand("timer ?");
        }
    }

    private function SendCommand(string $cmd): void
    {
        if (!$this->HasActiveParent()) return;
        
        $msg = [
            'DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}',
            'Buffer' => $cmd . "\r\n"
        ];
        $this->SendDataToParent(json_encode($msg));
        $this->SendDebug("Transmit", $cmd, 0);
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        
        if ($data['DataID'] == '{018EF6B5-AB94-40C6-AA53-46943E824ACF}') {
            $buffer = $data['Buffer'];
            
            // Fallback für den Fall, dass der User einen Hex-Cutter vorgeschaltet hat
            // (Erklärt warum im Debug 4E4F4B45590D0A ohne Leerzeichen ankommt)
            if (preg_match('/^[0-9A-Fa-f]+$/', $buffer) && strlen($buffer) % 2 === 0) {
                $buffer = hex2bin($buffer);
            }
            
            $this->SendDebug("Receive", $buffer, 0);
            
            // Mit bisherigem Puffer zusammenführen
            $current = $this->GetBuffer('DataBuffer') . $buffer;
            
            // Nach \n (Zeilenumbruch) suchen
            while (($pos = strpos($current, "\n")) !== false) {
                // Zeile extrahieren
                $line = substr($current, 0, $pos);
                // Rest wieder in den Puffer
                $current = substr($current, $pos + 1);
                
                // Carriage Returns etc. entfernen
                $line = trim(str_replace("\r", "", $line));
                if (!empty($line)) {
                    $this->SendDebug("Parse", "Verarbeite Zeile: " . $line, 0);
                    $this->ParseLine($line);
                }
            }
            
            $this->SetBuffer('DataBuffer', $current);
        }
    
        return "";
    }

    private function ParseLine(string $line): void
    {
        $cleanLine = trim($line, '"');
        
        if ($cleanLine === 'ok') {
            return;
        }

        if (in_array($cleanLine, ['err_cmd', 'err_inactive', 'NOKEY'])) {
            $this->SendDebug("ParseError", "Beamer meldet Fehler / Ablehnung: " . $cleanLine . " (Mögliche Ursache: Beamer ist im Standby oder Befehl ungültig)", 0);
            $this->Log("Beamer meldet Fehler / Ablehnung: " . $cleanLine . " (Mögliche Ursache: Beamer ist im Standby oder Befehl ungültig)");
            return;
        }

        // Power Status
        if (in_array($cleanLine, ['standby', 'startup', 'on', 'cooling1', 'cooling2', 'saving_standby'])) {
            $isPowered = ($cleanLine === 'on' || $cleanLine === 'startup');
            if ($this->GetValue('Power') !== $isPowered) {
                $this->SetValue('Power', (bool)$isPowered);
                $this->UpdateVisibility($isPowered);
            }
            return;
        }

        // Inputs
        $inputKey = array_search($cleanLine, $this->inputMap);
        if ($inputKey !== false) {
             if ($this->GetValue('Input') !== $inputKey) {
                 $this->SetValue('Input', $inputKey);
             }
             return;
        }

        // Picture Mode
        $picKey = array_search($cleanLine, $this->pictureModeMap);
        if ($picKey !== false) {
             if ($this->GetValue('PictureMode') !== $picKey) {
                 $this->SetValue('PictureMode', $picKey);
             }
             return;
        }

        // Timer (JSON Array)
        if (strpos($line, '[') === 0 && strpos($line, '{') !== false) {
             $arr = json_decode($line, true);
             if (is_array($arr)) {
                 foreach ($arr as $item) {
                     if (isset($item['operation'])) {
                         $this->SetValue('OperationTime', (int)$item['operation']);
                     }
                     if (isset($item['light_src'])) {
                         $this->SetValue('LightSourceTime', (int)$item['light_src']);
                     }
                 }
             }
             return;
        }

        // Error / Warning (JSON Array aus Strings)
        if (strpos($line, '[') === 0 && strpos($line, '{') === false) {
             $arr = json_decode($line, true);
             if (is_array($arr) && count($arr) > 0) {
                 $this->SetValue('Warning', $arr[0]);
             }
             return;
        }
    }

    protected function LogMessage(string $Message, int $Type): bool
    {
        IPS_LogMessage('SmartVillaKunterbunt', 'SonyBeamer: ' . $Message);
        return true;
    }

    public function GetConfigurationForm(): string
    {
        return <<<'EOT'
{
    "elements": [
        {
            "type": "RowLayout",
            "items": [
                {
                    "type": "NumberSpinner",
                    "name": "UpdateInterval",
                    "caption": "Update Intervall (Sekunden)"
                }
            ]
        }
    ],
    "actions": [
        {
            "type": "Button",
            "label": "Status jetzt aktualisieren",
            "onClick": "SONY_UpdateStatus($id);"
        }
    ]
}
EOT;
    }
}
