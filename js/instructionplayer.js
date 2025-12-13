// instructionPlayer.js
// Audio-only script engine (Howler.js required)

class InstructionPlayer {
  constructor(config, options = {}) {
    this.config = config;
    this.base = config.audioBase || "";
    this.snippets = config.snippets || {};
    this.varAudioMap = config.varAudioMap || {};
    this.log = options.log || (() => {});
  }

  // --- public ---
  async runInstructionById(id, context = {}) {
    const instr = (this.config.instructions || []).find(x => x.id === id);
    if (!instr) throw new Error(`Instruction not found: ${id}`);
    return this.runInstruction(instr, context);
  }

  async runInstruction(instr, context = {}) {
    const vars = { ...(instr.vars || {}) };

    const env = {
      context,
      vars,
      includes: (haystack, needle) =>
        typeof haystack === "string" && typeof needle === "string"
          ? haystack.includes(needle)
          : false
    };

    await this._runSteps(instr.script || [], env);
  }

  // --- core ---
  async _runSteps(steps, env) {
    for (const step of steps) {
      await this._runStep(step, env);
    }
  }

  async _runStep(step, env) {
    const t = step.type;

    if (t === "audio") {
      const path = this._snippetPath(step.name);
      this.log("[audio]", { name: step.name, path });
      await this._play(path);
      return;
    }

    // audioVar: plays a variable via varAudioMap
    // example: { type:"audioVar", var:"TARGET" } -> vars.TARGET -> "<b>" -> "letter-b.mp3"
    if (t === "audioVar") {
      const v = step.var;
      const token = env.vars[v];
      const file = this.varAudioMap[token];
      if (!file) throw new Error(`No varAudioMap entry for token '${token}' (var ${v})`);
      const path = this.base + file;
      this.log("[audioVar]", { var: v, token, path });
      await this._play(path);
      return;
    }

    // set: compute vars[var] from expression
    if (t === "set") {
      const varName = step.var;
      const value = this._evalExpr(step.expr, env);
      env.vars[varName] = value;
      this.log("[set]", { var: varName, value, expr: step.expr });
      return;
    }

    // choice: chooses then/else based on expr
    if (t === "choice") {
      const ok = Boolean(this._evalExpr(step.expr, env));
      this.log("[choice]", { expr: step.expr, result: ok });
      const branch = ok ? (step.then || []) : (step.else || []);
      await this._runSteps(branch, env);
      return;
    }

    // pause
    if (t === "pause") {
      const ms = Number(step.ms ?? 0);
      this.log("[pause]", { ms });
      await new Promise(r => setTimeout(r, ms));
      return;
    }

    throw new Error(`Unknown step type: ${t}`);
  }

  // --- helpers ---
  _snippetPath(name) {
    const file = this.snippets[name];
    if (!file) throw new Error(`Unknown snippet: ${name}`);
    return this.base + file;
  }

  _evalExpr(expr, env) {
    const display = env.context.display ?? "";
    const vars = env.vars;
    const includes = env.includes;
    if (!expr) return undefined;

    // trusted JSON only
    // eslint-disable-next-line no-new-func
    const fn = new Function("display", "vars", "includes", `return (${expr});`);
    return fn(display, vars, includes);
  }

  _play(path) {
    return new Promise((resolve, reject) => {
      if (typeof Howl === "undefined") {
        reject(new Error("Howler.js not loaded (Howl is undefined)"));
        return;
      }
      const sound = new Howl({
        src: [path],
        html5: true,
        onend: resolve,
        onloaderror: (_, err) => reject(err),
        onplayerror: (_, err) => reject(err)
      });
      sound.play();
    });
  }
}

window.InstructionPlayer = InstructionPlayer;