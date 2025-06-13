<!DOCTYPE html>
<html lang="de">
<!--
### my Prompts:
bitte sieh dir die Web Serial API an

###
schreibe mir eine web-app
sie soll eine eingabe textarea mit senden button haben, direkt darüber eine textarea wo aowohl eingaben als auch ausgaben mit zeitstempel gezeigt werden, eine checkbox für autoscroll und ein button "clear" zusätzlich soll es eine js funktion geben die unter der eingabebox knöpfe hinzufügt, die onclick messages senden zB "A\n" es soll auch eine funktion geben die slider hinzufügt, man soll mindestwert, maximalwert und "delay" angeben können (damit zb nur maximal alle 100ms ein neuer wert gesendet wird) bei jedem slider soll es eine checkbox "auto update" geben und einen button "update" beim klick auf den update-button soll der wert und der name des sliders gesendet werden, zB.  "S35", wenn "auto update" enabled ist, soll es bei jeder änderung senden, aber maximal alle "delay" ms
mach bitte auch ein entsprechendes css und erstelle keine seperaten files sondern gib alles in eine große html datei

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
    button, input[type="checkbox"] {
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
      min-width: 80px;
    }
  </style>
</head>
<body>

  <h2>Web Serial Terminal</h2>
  <button onclick="connectSerial()">🔌 Verbinden</button>
  <button onclick="clearLog()">🧹 Clear</button>
  <label><input type="checkbox" id="autoscroll" checked> Autoscroll</label>
  <br>
  <div id="log" readonly></div>
  <textarea id="input" placeholder="Nachricht eingeben..."></textarea>
  <button onclick="sendMessage()">📤 Senden</button>

  <div class="control-panel">
    <h3>Custom Buttons</h3>
    <div id="customButtons"></div>
    <button onclick="addCustomButton('A\\n')">Add Button "A\n"</button>

    <h3>Sliders</h3>
    <div id="sliders"></div>
    <button onclick="addSlider('S', 0, 100, 200)">Add Slider "S"</button>
  </div>

  <script>
    let port, writer, reader;
    let textDecoder, readLoopActive = false;

    async function connectSerial() {
      try {
        port = await navigator.serial.requestPort();
        await port.open({ baudRate: 9600 });

        textDecoder = new TextDecoderStream();
        const readableStreamClosed = port.readable.pipeTo(textDecoder.writable);
        reader = textDecoder.readable.getReader();

        const encoder = new TextEncoderStream();
        const writableStreamClosed = encoder.readable.pipeTo(port.writable);
        writer = encoder.writable.getWriter();

        readLoop();
        log("🔌 Serial connected.");
      } catch (e) {
        log("❌ Fehler: " + e);
      }
    }

    function log(text) {
      const timestamp = new Date().toLocaleTimeString();
      const logDiv = document.getElementById("log");
      logDiv.textContent += `[${timestamp}] ${text}\n`;
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
          if (value) log(value.trim());
        } catch (e) {
          log("❌ Lese-Fehler: " + e);
          break;
        }
      }
    }

    function clearLog() {
      document.getElementById("log").textContent = '';
    }

    async function sendMessage() {
      const input = document.getElementById("input");
      const msg = input.value;
      input.value = '';
      if (!writer) {
        log("⚠️ Kein Writer verbunden.");
        return;
      }
      await writer.write(msg);
      log("Gesendet: " + msg.replace(/\n/g, "\\n"));
    }

    function addCustomButton(text) {
      const div = document.createElement("div");
      const btn = document.createElement("button");
      btn.textContent = `Send "${text.replace("\\n", "\\n")}"`;
      btn.onclick = () => {
        if (writer) {
          writer.write(text.replace("\\n", "\n"));
          log("Gesendet: " + text);
        } else {
          log("⚠️ Kein Writer verbunden.");
        }
      };
      div.appendChild(btn);
      document.getElementById("customButtons").appendChild(div);
    }

    function addSlider(name, min, max, delayMs) {
      const container = document.createElement("div");
      container.className = "slider-block";

      const label = document.createElement("label");
      label.textContent = name;

      const input = document.createElement("input");
      input.type = "range";
      input.min = min;
      input.max = max;
      input.value = min;

      const valueSpan = document.createElement("span");
      valueSpan.textContent = input.value;

      const autoCheck = document.createElement("input");
      autoCheck.type = "checkbox";
      autoCheck.id = "auto_" + name;

      const updateBtn = document.createElement("button");
      updateBtn.textContent = "Update";

      container.appendChild(label);
      container.appendChild(input);
      container.appendChild(valueSpan);
      container.appendChild(document.createTextNode(" Auto-Update "));
      container.appendChild(autoCheck);
      container.appendChild(updateBtn);

      let lastSent = 0;

      input.addEventListener("input", () => {
        valueSpan.textContent = input.value;
        if (autoCheck.checked) {
          const now = Date.now();
          if (now - lastSent >= delayMs) {
            sendSliderValue(name, input.value);
            lastSent = now;
          }
        }
      });

      updateBtn.onclick = () => {
        sendSliderValue(name, input.value);
        lastSent = Date.now();
      };

      document.getElementById("sliders").appendChild(container);
    }

    function sendSliderValue(name, value) {
      const msg = `${name}${value}`;
      if (writer) {
        writer.write(msg + "\n");
        log(`Gesendet: ${msg}`);
      } else {
        log("⚠️ Kein Writer verbunden.");
      }
    }
  </script>
</body>
</html>
