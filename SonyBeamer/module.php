<?php

declare(strict_types=1);

class SonyBeamer extends IPSModuleStrict
{
    public function GetCompatibleParents(): string
    {
        return '["{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}"]';
    }
    public function Create(): void{
        parent::Create();

        // Eigenschaften
        $this->RegisterPropertyInteger('UpdateInterval', 30);

        // Timer fr Polling
        $this->RegisterTimer('UpdateTimer', 0, 'SONY_UpdateStatus($_IPS[\'TARGET\']);');

        // Puffer fr TCP-Fragmente
        $this->SetBuffer('DataBuffer', '');



        // Variablen registrieren
        

        

        $this->RegisterVariableBoolean('Power', '📺 Status', '', 10);
        $this->EnableAction('Power');

        $this->RegisterVariableString('Input', '🔌 Eingang', '', 20);
        $this->EnableAction('Input');

        $this->RegisterVariableString('PictureMode', '🖼️ Bildmodus', '', 30);
        $this->EnableAction('PictureMode');

        $this->RegisterVariableInteger('OperationTime', '⏱️ Betriebsstunden', '', 40);
        $this->RegisterVariableInteger('LightSourceTime', '💡 Lampenstunden', '', 50);
        $this->RegisterVariableString('Warning', '⚠️ Warnungen', '', 60);
    }

    public function ApplyChanges(): void{
        parent::ApplyChanges();

        $interval = $this->ReadPropertyInteger('UpdateInterval');
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
        
                IPS_SetVariableCustomPresentation($this->GetIDForIdent('Input'), [
            'ICON' => 'Plug',
            'ASSOCIATIONS' => [
                ['VALUE' => 'hdmi1', 'NAME' => 'HDMI 1', 'ICON' => '', 'COLOR' => -1],
                ['VALUE' => 'hdmi2', 'NAME' => 'HDMI 2', 'ICON' => '', 'COLOR' => -1],
                ['VALUE' => 'video1', 'NAME' => 'Video 1', 'ICON' => '', 'COLOR' => -1],
                ['VALUE' => 'component', 'NAME' => 'Component', 'ICON' => '', 'COLOR' => -1]
            ]
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('PictureMode'), [
            'ICON' => 'TV',
            'ASSOCIATIONS' => [
                ['VALUE' => 'dynamic', 'NAME' => 'Dynamic', 'ICON' => '', 'COLOR' => -1],
                ['VALUE' => 'standard', 'NAME' => 'Standard', 'ICON' => '', 'COLOR' => -1],
                ['VALUE' => 'brt_priority', 'NAME' => 'Brightness Priority', 'ICON' => '', 'COLOR' => -1],
                ['VALUE' => 'cinema_film_1', 'NAME' => 'Cinema Film 1', 'ICON' => '', 'COLOR' => -1],
                ['VALUE' => 'cinema_film_2', 'NAME' => 'Cinema Film 2', 'ICON' => '', 'COLOR' => -1],
                ['VALUE' => 'reference', 'NAME' => 'Reference', 'ICON' => '', 'COLOR' => -1],
                ['VALUE' => 'tv', 'NAME' => 'TV', 'ICON' => '', 'COLOR' => -1],
                ['VALUE' => 'photo', 'NAME' => 'Photo', 'ICON' => '', 'COLOR' => -1],
                ['VALUE' => 'game', 'NAME' => 'Game', 'ICON' => '', 'COLOR' => -1],
                ['VALUE' => 'bright_cinema', 'NAME' => 'Bright Cinema', 'ICON' => '', 'COLOR' => -1],
                ['VALUE' => 'bright_tv', 'NAME' => 'Bright TV', 'ICON' => '', 'COLOR' => -1],
                ['VALUE' => 'user', 'NAME' => 'User', 'ICON' => '', 'COLOR' => -1]
            ]
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
                $this->SendCommand("input \"$Value\"");
                $this->Log("Eingang auf $Value gesetzt.");
                break;
            case 'PictureMode':
                $this->SendCommand("picture_mode \"$Value\"");
                $this->Log("Bildmodus auf $Value gesetzt.");
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
            $this->SendDebug("Log", "UpdateStatus abgebrochen: Kein aktives bergeordnetes Gateway gefunden!", 0);
            return;
        }

        $this->SendDebug("Log", "Sende Status-Abfragen an Beamer...", 0);
        $this->SendCommand("power_status ?");
        IPS_Sleep(100);
        $this->SendCommand("input ?");
        IPS_Sleep(100);
        $this->SendCommand("picture_mode ?");
        IPS_Sleep(100);
        $this->SendCommand("error ?");
        IPS_Sleep(100);
        $this->SendCommand("timer ?");
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
            $buffer = utf8_decode($data['Buffer']);
            $this->SendDebug("Receive", $buffer, 0);
            
            // Mit bisherigem Puffer zusammenfhren
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
        
        if ($cleanLine === 'ok' || $cleanLine === 'err_cmd' || $cleanLine === 'err_inactive') return;

        // Power Status
        if (in_array($cleanLine, ['standby', 'startup', 'on', 'cooling1', 'cooling2', 'saving_standby'])) {
            $isPowered = ($cleanLine === 'on' || $cleanLine === 'startup');
            if ($this->GetValue('Power') !== $isPowered) {
                $this->SetValue('Power', $isPowered);
                $this->UpdateVisibility($isPowered);
            }
            return;
        }

        // Inputs
        if (strpos($cleanLine, 'hdmi') === 0 || strpos($cleanLine, 'video') === 0 || strpos($cleanLine, 'component') === 0) {
             if ($this->GetValue('Input') !== $cleanLine) {
                 $this->SetValue('Input', $cleanLine);
             }
             return;
        }

        // Picture Mode
        if (in_array($cleanLine, ['dynamic', 'standard', 'brt_priority', 'cinema_film_1', 'cinema_film_2', 'reference', 'tv', 'photo', 'game', 'bright_cinema', 'bright_tv', 'user'])) {
             if ($this->GetValue('PictureMode') !== $cleanLine) {
                 $this->SetValue('PictureMode', $cleanLine);
             }
             return;
        }

        // Timer (JSON Array)
        if (strpos($line, '[') === 0 && strpos($line, '{') !== false) {
             $arr = json_decode($line, true);
             if (is_array($arr)) {
                 foreach ($arr as $item) {
                     if (isset($item['operation'])) {
                         $this->SetValue('OperationTime', $item['operation']);
                     }
                     if (isset($item['light_src'])) {
                         $this->SetValue('LightSourceTime', $item['light_src']);
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
}

