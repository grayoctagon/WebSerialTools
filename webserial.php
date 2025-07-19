<!DOCTYPE html>
<html lang="de">
<!--
### my Prompts:
bitte sieh dir die Web Serial API an

###
schreibe mir eine web-app
sie soll eine eingabe textarea mit senden button haben, direkt darüber eine textarea wo aowohl eingaben als auch ausgaben mit zeitstempel gezeigt werden, eine checkbox für autoscroll und ein button "clear" zusätzlich soll es eine js funktion geben die unter der eingabebox knöpfe hinzufügt, die onclick messages senden zB "A\n" es soll auch eine funktion geben die slider hinzufügt, man soll mindestwert, maximalwert und "delay" angeben können (damit zb nur maximal alle 100ms ein neuer wert gesendet wird) bei jedem slider soll es eine checkbox "auto update" geben und einen button "update" beim klick auf den update-button soll der wert und der name des sliders gesendet werden, zB.  "S35", wenn "auto update" enabled ist, soll es bei jeder änderung senden, aber maximal alle "delay" ms
mach bitte auch ein entsprechendes css und erstelle keine seperaten files sondern gib alles in eine große html datei

###
füge bitte links neben dem verbinden button ein dropdown ein in dem man die baudrate wählen kann(es sollen alle sein die die arduino ide anbietet), neben 115200 und neben 9600 soll in der anzeige ein stern emoji sein, 115200 soll der standard wert sein

###
aktuell werden nachrichten vom arduino in mehrere zeilen geteilt
um das zu vermeiden passe bitte die log funktion an, füge einen parameter "continue" hinzu, default soll dieser false sein, aber beim aufruf aus der readLoop funktion soll er true sein, wenn er true ist und die nachricht davor auch mit continue gelogged wurde, dann fange nur neue zeilen an, wo ein Zeilenumbruch (\n) vorkommt
und mach bitte kein "value.trim()" in readLoop() das könne sonst zeilenumbrüche entfernen

###
bitte mach bei den slider-blocks und auch bei den buttons die Labels variabel, sie sollen in einem input feld definiert werden
bitte füge bei den slider-block auch inputs hinzu die das minimum und maximum definieren

-->
<head>
  <meta charset="UTF-8">
  <title>Web Serial Terminal</title>
  <style>
    body {
      font-family: sans-serif;
      background: #1e1e1e;
      color: #e0e0e0;
      padding: 20px;
    }
    textarea {
      width: 100%;
      box-sizing: border-box;
      font-family: monospace;
      margin-bottom: 10px;
    }
    #configTA {
      height: 360px;
    }
    #log {
      height: 200px;
      background: #111;
      padding: 10px;
      overflow-y: auto;
      white-space: pre-wrap;
    }
    #input {
      height: 60px;
    }
    button, select, input[type="checkbox"], input[type="text"], input[type="number"] {
      margin-right: 10px;
      margin-bottom: 10px;
    }
    .control-panel {
      margin-top: 10px;
    }
    .slider-block {
      background: #2a2a2a;
      padding: 10px;
      margin-bottom: 10px;
      border-radius: 5px;
    }
    .slider-block label {
      display: inline-block;
      min-width: 120px;
    }
    .sliderValueSpan{
      min-width: 40px;
      display: inline-block;
    }
    .form-row {
      margin-bottom: 10px;
    }
    .showArea {
      border: 1px solid red;
      padding: 5px;
      display: inline-block;
    }
    .sliderEl {
      width: 1000px;
    }
  </style>
</head>
<body>

  <h2>Web Serial Terminal</h2>
  <select id="baudrate">
    <option value="300">300</option>
    <option value="1200">1200</option>
    <option value="2400">2400</option>
    <option value="4800">4800</option>
    <option value="9600">9600 ⭐️</option>
    <option value="14400">14400</option>
    <option value="19200">19200</option>
    <option value="28800">28800</option>
    <option value="38400">38400</option>
    <option value="57600">57600</option>
    <option value="74880">74880</option>
    <option value="115200" selected>115200 ⭐️</option>
    <option value="230400">230400</option>
    <option value="250000">250000</option>
    <option value="500000">500000</option>
    <option value="1000000">1000000</option>
  </select>
  <button onclick="connectSerial()">🔌 Verbinden</button>
  <button onclick="disconnecSerial()">✂️ Trennen</button>
  <button onclick="clearLog()">🧹 Clear</button>
  <label><input type="checkbox" id="autoscroll" checked> Autoscroll</label>
  <br>
  <div id="log" readonly></div>
  <textarea id="inputTA" placeholder="Nachricht eingeben..."></textarea>
  <button onclick="sendMessage()">📤 Senden</button>

  <div class="control-panel">
    <h3>Custom Buttons</h3>
    <div class="form-row">
      Label: <input type="text" id="buttonLabel" placeholder="Button Text" value="Send A">
      Message: <input type="text" id="buttonMessage" placeholder="Nachricht" value="A\n">
      <button onclick="handleAddCustomButton()">➕ Button hinzufügen</button>
    </div>
    <div id="customButtons" class="showArea"></div>

    <h3>Sliders</h3>
    <div class="form-row">
      Label: <input type="text" id="sliderLabel" value="S">
      Min: <input type="number" id="sliderMin" value="1">
      Max: <input type="number" id="sliderMax" value="100">
      Delay (ms): <input type="number" id="sliderDelay" value="200">
      <button onclick="handleAddSlider()">➕ Slider hinzufügen</button>
    </div>
    <div id="sliders" class="showArea"></div>
  </div>
  <div class="control-panel">
    <h3>config:</h3>
    <textarea id="configTA" placeholder=""></textarea>
    <button onclick="configApplyTextarea()">⚙️apply</button>
    <button onclick="configSave()">💾save</button>
  </div>

  <script>
    let port, writer, reader;
    let textDecoder, readLoopActive = false;
    let lastWasContinue = false;
    let lineBuffer = '';
    let readableStreamClosed, writableStreamClosed;
    let myConfig={
      "buttons":[],
      "sliders":[],
    };
    storageToConfig();

    async function connectSerial() {
      try {
        port = await navigator.serial.requestPort();
        const baud = parseInt(document.getElementById("baudrate").value);
        await port.open({ baudRate: baud });

        textDecoder = new TextDecoderStream();
        readableStreamClosed = port.readable.pipeTo(textDecoder.writable);
        reader = textDecoder.readable.getReader();

        const encoder = new TextEncoderStream();
        writableStreamClosed = encoder.readable.pipeTo(port.writable);
        writer = encoder.writable.getWriter();

        readLoop();
        log(`🔌 Serial connected at ${baud} Baud.`);
      } catch (e) {
        log("❌ Fehler: " + e);
      }
    }
    
    async function disconnecSerial() {
      try {
        readLoopActive = false;

        if (reader) {
          try {
            await reader.cancel();
          } catch (e) {
            log("⚠️ Fehler beim Abbrechen des Lesens: " + e);
          }
          reader.releaseLock();
          reader = null;
        }

        if (writer) {
          try {
            await writer.close();
          } catch (e) {
            log("⚠️ Fehler beim Schließen des Writers: " + e);
          }
          writer.releaseLock();
          writer = null;
        }

        // Jetzt WARTEN auf das Beenden der Streams
        if (readableStreamClosed) {
          await readableStreamClosed.catch(() => { });
          readableStreamClosed = null;
        }

        if (writableStreamClosed) {
          await writableStreamClosed.catch(() => { });
          writableStreamClosed = null;
        }

        if (port) {
          await port.close();
          port = null;
        }

        log("✂️ Verbindung getrennt.");
      } catch (e) {
        log("❌ Fehler beim Trennen: " + e);
      }
    }

    function log(text, cont = false) {
      const logDiv = document.getElementById("log");

      if (cont && lastWasContinue) {
        // Splitte nur bei \n und baue aus dem Buffer
        lineBuffer += text;
        const parts = lineBuffer.split(/\n/);
        lineBuffer = parts.pop(); // letzte unvollständige Zeile bleibt im Puffer
        for (const part of parts) {
          const timestamp = new Date().toLocaleTimeString();
          logDiv.textContent += `[${timestamp}] ${part}\n`;
        }
      } else {
        const timestamp = new Date().toLocaleTimeString();
        logDiv.textContent += `[${timestamp}] ${text}\n`;
        lineBuffer = ''; // reset wenn es kein fortlaufender Block ist
      }

      lastWasContinue = cont;

      if (document.getElementById("autoscroll").checked) {
        logDiv.scrollTop = logDiv.scrollHeight;
      }
    }

    async function readLoop() {
      readLoopActive = true;
      while (readLoopActive) {
        try {
          const { value, done } = await reader.read();
          if (done) break;
          if (value) log(value, true); // wichtig: continue=true
        } catch (e) {
          log("❌ Lese-Fehler: " + e);
          break;
        }
      }
    }

    function clearLog() {
      document.getElementById("log").textContent = '';
      lineBuffer = '';
      lastWasContinue = false;
    }

    async function sendMessage() {
      const input = document.getElementById("inputTA");
      const msg = input.value;
      input.value = '';
      if (!writer) {
        log("⚠️ Kein Writer verbunden.");
        return;
      }
      await writer.write(msg);
      log("Gesendet: " + msg.replace(/\n/g, "\\n"));
    }

    function handleAddCustomButton() {
      const label = document.getElementById("buttonLabel").value || "Send";
      const message = document.getElementById("buttonMessage").value || "";
      addCustomButton(label, message);
    }

    function addCustomButton(label, message, addToConfig=true) {
      if(addToConfig)
      myConfig.buttons.push(
        {
          label:label,
          message:message,
        }
      );
      cofigToTextarea();
      const div = document.createElement("div");
      const btn = document.createElement("button");
      btn.textContent = label;
      btn.onclick = () => {
        if (writer) {
          writer.write(message.replace(/\\n/g, "\n"));
          log("Gesendet: " + message);
        } else {
          log("⚠️ Kein Writer verbunden.");
        }
      };
      div.appendChild(btn);
      document.getElementById("customButtons").appendChild(div);
    }

    function handleAddSlider() {
      const name = document.getElementById("sliderLabel").value || "S";
      const min = parseInt(document.getElementById("sliderMin").value) || 0;
      const max = parseInt(document.getElementById("sliderMax").value) || 100;
      const delay = parseInt(document.getElementById("sliderDelay").value) || 200;
      addSlider(name, min, max, delay);
    }

    function addSlider(name, min, max, delayMs, addToConfig = true, currentValue = min, autoUpdate = false) {
      if (addToConfig) {
        myConfig.sliders.push({
          name: name,
          min: min,
          max: max,
          delayMs: delayMs,
          value: currentValue,
          auto: autoUpdate
        });
        cofigToTextarea();
      }

      const container = document.createElement("div");
      container.className = "slider-block";

      const label = document.createElement("label");
      label.textContent = name;

      const valueSpan = document.createElement("span");
      valueSpan.textContent = currentValue;
      valueSpan.classList.add("sliderValueSpan");

      const input = document.createElement("input");
      input.type = "range";
      input.min = min;
      input.max = max;
      input.value = currentValue;
      input.classList.add("sliderEl");

      const autoCheck = document.createElement("input");
      autoCheck.type = "checkbox";
      autoCheck.checked = autoUpdate;

      const updateBtn = document.createElement("button");
      updateBtn.textContent = "Update";

      container.appendChild(label);
      container.appendChild(valueSpan);
      container.appendChild(input);
      container.appendChild(document.createTextNode(" Auto-Update "));
      container.appendChild(autoCheck);
      container.appendChild(updateBtn);

      let lastSent = 0;

      function updateSliderStateInConfig() {
        let s = myConfig.sliders.find(sl => sl.name === name);
        if (s) {
          s.value = parseInt(input.value);
          s.auto = autoCheck.checked;
        }
      }

      input.addEventListener("input", () => {
        valueSpan.textContent = input.value;
        updateSliderStateInConfig();
        if (autoCheck.checked) {
          const now = Date.now();
          if (now - lastSent >= delayMs) {
            sendSliderValue(name, input.value);
            lastSent = now;
          }else{
            //todo: hold value and send once delay is over, when it was the last before stopped, otherwise the last movemend may get lost on fast movements
          }
        }
      });

      updateBtn.onclick = () => {
        sendSliderValue(name, input.value);
        lastSent = Date.now();
      };

      autoCheck.addEventListener("change", updateSliderStateInConfig);

      // 🔁 Wheel scroll: +1 or -1
      input.addEventListener("wheel", (e) => {
        e.preventDefault(); // prevent page scroll

        const step = e.deltaY < 0 ? 1 : -1;
        let newValue = parseInt(input.value) + step;

        // Clamp value between min and max
        newValue = Math.min(Math.max(newValue, parseInt(input.min)), parseInt(input.max));
        input.value = newValue;
        valueSpan.textContent = newValue;
        updateSliderStateInConfig();
        if (autoCheck.checked) {
          const now = Date.now();
          if (now - lastSent >= delayMs) {
            sendSliderValue(name, newValue);
            lastSent = now;
          }
        }
      });

      document.getElementById("sliders").appendChild(container);
    }

    function sendSliderValue(name, value) {
      console.log("sendSliderValue",value);
      const msg = `${name}${value}`;
      if (writer) {
        writer.write(msg + "\n");
        log(`Gesendet: ${msg}`);
      } else {
        log("⚠️ Kein Writer verbunden.");
      }
    }
    
    function cofigToTextarea(){
      const outTA = document.getElementById("configTA");
      outTA.value=JSON.stringify(myConfig,null,"\t");
      localStorage.setItem("WebSerialKonfig",JSON.stringify(myConfig));
    }
    
    function storageToConfig(){
      const outTA = document.getElementById("configTA");
      let c=localStorage.getItem("WebSerialKonfig");
      if(c)
        try {
          myConfig=JSON.parse(c);
          outTA.value=JSON.stringify(myConfig,null,"\t");
          configApply();
        } catch (error) {
          console.error(error);
        }
    }
    
    function configSave() {
      try {
        const configText = document.getElementById("configTA").value;
        let j=JSON.parse(configText);
        localStorage.setItem("WebSerialKonfig", JSON.stringify(j));
        log("💾 Konfiguration gespeichert.");
      } catch (err) {
        log("❌ Fehler beim Speichern der Konfiguration: " + err.message);
        alert("❌ Fehler beim Speichern der Konfiguration: " + err.message)
      }
    }
    
    function configApplyTextarea() {
      try {
        const configText = document.getElementById("configTA").value;
        myConfig=JSON.parse(configText);
        configApply();
      } catch (err) {
        log("❌ Fehler lesen der Konfiguration aus der Textarea: " + err.message);
        alert("❌ Fehler lesen der Konfiguration aus der Textarea: " + err.message)
      }
    }
    
    function configApply() {
      try {
          let cb=document.getElementById("customButtons");
          cb.innerHTML="";
          myConfig.buttons.forEach(e=>{
            addCustomButton(e.label,e.message,false);
          });
          let sliders=document.getElementById("sliders");
          sliders.innerHTML="";
          myConfig.sliders.forEach(e=>{
            addSlider(e.name, e.min, e.max, e.delayMs, false, e.value ?? e.min, e.auto ?? false);
          });
        } catch (error) {
          console.error(error);
        }
    }
  </script>
</body>
</html>
