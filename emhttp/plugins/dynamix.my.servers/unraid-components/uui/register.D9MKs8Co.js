var $a = (e) => {
  throw TypeError(e);
};
var wc = (e, t, n) => t.has(e) || $a("Cannot " + n);
var oo = (e, t, n) => (wc(e, t, "read from private field"), n ? n.call(e) : t.get(e)), Ta = (e, t, n) => t.has(e) ? $a("Cannot add the same private member more than once") : t instanceof WeakSet ? t.add(e) : t.set(e, n);
/**
* @vue/shared v3.5.13
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
/*! #__NO_SIDE_EFFECTS__ */
// @__NO_SIDE_EFFECTS__
function Rs(e) {
  const t = /* @__PURE__ */ Object.create(null);
  for (const n of e.split(",")) t[n] = 1;
  return (n) => n in t;
}
const Ae = {}, zn = [], It = () => {
}, _c = () => !1, ur = (e) => e.charCodeAt(0) === 111 && e.charCodeAt(1) === 110 && // uppercase letter
(e.charCodeAt(2) > 122 || e.charCodeAt(2) < 97), Is = (e) => e.startsWith("onUpdate:"), Ke = Object.assign, Ls = (e, t) => {
  const n = e.indexOf(t);
  n > -1 && e.splice(n, 1);
}, xc = Object.prototype.hasOwnProperty, Ce = (e, t) => xc.call(e, t), re = Array.isArray, jn = (e) => cr(e) === "[object Map]", Li = (e) => cr(e) === "[object Set]", ae = (e) => typeof e == "function", Fe = (e) => typeof e == "string", tn = (e) => typeof e == "symbol", Ie = (e) => e !== null && typeof e == "object", Fi = (e) => (Ie(e) || ae(e)) && ae(e.then) && ae(e.catch), Vi = Object.prototype.toString, cr = (e) => Vi.call(e), Cc = (e) => cr(e).slice(8, -1), dr = (e) => cr(e) === "[object Object]", Fs = (e) => Fe(e) && e !== "NaN" && e[0] !== "-" && "" + parseInt(e, 10) === e, uo = /* @__PURE__ */ Rs(
  // the leading comma is intentional so empty string "" is also included
  ",key,ref,ref_for,ref_key,onVnodeBeforeMount,onVnodeMounted,onVnodeBeforeUpdate,onVnodeUpdated,onVnodeBeforeUnmount,onVnodeUnmounted"
), fr = (e) => {
  const t = /* @__PURE__ */ Object.create(null);
  return (n) => t[n] || (t[n] = e(n));
}, Sc = /-(\w)/g, qe = fr(
  (e) => e.replace(Sc, (t, n) => n ? n.toUpperCase() : "")
), $c = /\B([A-Z])/g, pt = fr(
  (e) => e.replace($c, "-$1").toLowerCase()
), pr = fr((e) => e.charAt(0).toUpperCase() + e.slice(1)), co = fr(
  (e) => e ? `on${pr(e)}` : ""
), hn = (e, t) => !Object.is(e, t), Uo = (e, ...t) => {
  for (let n = 0; n < e.length; n++)
    e[n](...t);
}, Ni = (e, t, n, o = !1) => {
  Object.defineProperty(e, t, {
    configurable: !0,
    enumerable: !1,
    writable: o,
    value: n
  });
}, ss = (e) => {
  const t = parseFloat(e);
  return isNaN(t) ? e : t;
}, Pa = (e) => {
  const t = Fe(e) ? Number(e) : NaN;
  return isNaN(t) ? e : t;
};
let Ea;
const hr = () => Ea || (Ea = typeof globalThis < "u" ? globalThis : typeof self < "u" ? self : typeof window < "u" ? window : typeof global < "u" ? global : {});
function nt(e) {
  if (re(e)) {
    const t = {};
    for (let n = 0; n < e.length; n++) {
      const o = e[n], r = Fe(o) ? Ac(o) : nt(o);
      if (r)
        for (const s in r)
          t[s] = r[s];
    }
    return t;
  } else if (Fe(e) || Ie(e))
    return e;
}
const Tc = /;(?![^(]*\))/g, Pc = /:([^]+)/, Ec = /\/\*[^]*?\*\//g;
function Ac(e) {
  const t = {};
  return e.replace(Ec, "").split(Tc).forEach((n) => {
    if (n) {
      const o = n.split(Pc);
      o.length > 1 && (t[o[0].trim()] = o[1].trim());
    }
  }), t;
}
function le(e) {
  let t = "";
  if (Fe(e))
    t = e;
  else if (re(e))
    for (let n = 0; n < e.length; n++) {
      const o = le(e[n]);
      o && (t += o + " ");
    }
  else if (Ie(e))
    for (const n in e)
      e[n] && (t += n + " ");
  return t.trim();
}
function me(e) {
  if (!e) return null;
  let { class: t, style: n } = e;
  return t && !Fe(t) && (e.class = le(t)), n && (e.style = nt(n)), e;
}
const Oc = "itemscope,allowfullscreen,formnovalidate,ismap,nomodule,novalidate,readonly", Bc = /* @__PURE__ */ Rs(Oc);
function zi(e) {
  return !!e || e === "";
}
const ji = (e) => !!(e && e.__v_isRef === !0), xt = (e) => Fe(e) ? e : e == null ? "" : re(e) || Ie(e) && (e.toString === Vi || !ae(e.toString)) ? ji(e) ? xt(e.value) : JSON.stringify(e, Hi, 2) : String(e), Hi = (e, t) => ji(t) ? Hi(e, t.value) : jn(t) ? {
  [`Map(${t.size})`]: [...t.entries()].reduce(
    (n, [o, r], s) => (n[zr(o, s) + " =>"] = r, n),
    {}
  )
} : Li(t) ? {
  [`Set(${t.size})`]: [...t.values()].map((n) => zr(n))
} : tn(t) ? zr(t) : Ie(t) && !re(t) && !dr(t) ? String(t) : t, zr = (e, t = "") => {
  var n;
  return (
    // Symbol.description in es2019+ so we need to cast here to pass
    // the lib: es2016 check
    tn(e) ? `Symbol(${(n = e.description) != null ? n : t})` : e
  );
};
/**
* @vue/reactivity v3.5.13
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
let it;
class Wi {
  constructor(t = !1) {
    this.detached = t, this._active = !0, this.effects = [], this.cleanups = [], this._isPaused = !1, this.parent = it, !t && it && (this.index = (it.scopes || (it.scopes = [])).push(
      this
    ) - 1);
  }
  get active() {
    return this._active;
  }
  pause() {
    if (this._active) {
      this._isPaused = !0;
      let t, n;
      if (this.scopes)
        for (t = 0, n = this.scopes.length; t < n; t++)
          this.scopes[t].pause();
      for (t = 0, n = this.effects.length; t < n; t++)
        this.effects[t].pause();
    }
  }
  /**
   * Resumes the effect scope, including all child scopes and effects.
   */
  resume() {
    if (this._active && this._isPaused) {
      this._isPaused = !1;
      let t, n;
      if (this.scopes)
        for (t = 0, n = this.scopes.length; t < n; t++)
          this.scopes[t].resume();
      for (t = 0, n = this.effects.length; t < n; t++)
        this.effects[t].resume();
    }
  }
  run(t) {
    if (this._active) {
      const n = it;
      try {
        return it = this, t();
      } finally {
        it = n;
      }
    }
  }
  /**
   * This should only be called on non-detached scopes
   * @internal
   */
  on() {
    it = this;
  }
  /**
   * This should only be called on non-detached scopes
   * @internal
   */
  off() {
    it = this.parent;
  }
  stop(t) {
    if (this._active) {
      this._active = !1;
      let n, o;
      for (n = 0, o = this.effects.length; n < o; n++)
        this.effects[n].stop();
      for (this.effects.length = 0, n = 0, o = this.cleanups.length; n < o; n++)
        this.cleanups[n]();
      if (this.cleanups.length = 0, this.scopes) {
        for (n = 0, o = this.scopes.length; n < o; n++)
          this.scopes[n].stop(!0);
        this.scopes.length = 0;
      }
      if (!this.detached && this.parent && !t) {
        const r = this.parent.scopes.pop();
        r && r !== this && (this.parent.scopes[this.index] = r, r.index = this.index);
      }
      this.parent = void 0;
    }
  }
}
function Ki(e) {
  return new Wi(e);
}
function Vs() {
  return it;
}
function Ui(e, t = !1) {
  it && it.cleanups.push(e);
}
let Oe;
const jr = /* @__PURE__ */ new WeakSet();
class Gi {
  constructor(t) {
    this.fn = t, this.deps = void 0, this.depsTail = void 0, this.flags = 5, this.next = void 0, this.cleanup = void 0, this.scheduler = void 0, it && it.active && it.effects.push(this);
  }
  pause() {
    this.flags |= 64;
  }
  resume() {
    this.flags & 64 && (this.flags &= -65, jr.has(this) && (jr.delete(this), this.trigger()));
  }
  /**
   * @internal
   */
  notify() {
    this.flags & 2 && !(this.flags & 32) || this.flags & 8 || qi(this);
  }
  run() {
    if (!(this.flags & 1))
      return this.fn();
    this.flags |= 2, Aa(this), Xi(this);
    const t = Oe, n = Pt;
    Oe = this, Pt = !0;
    try {
      return this.fn();
    } finally {
      Ji(this), Oe = t, Pt = n, this.flags &= -3;
    }
  }
  stop() {
    if (this.flags & 1) {
      for (let t = this.deps; t; t = t.nextDep)
        js(t);
      this.deps = this.depsTail = void 0, Aa(this), this.onStop && this.onStop(), this.flags &= -2;
    }
  }
  trigger() {
    this.flags & 64 ? jr.add(this) : this.scheduler ? this.scheduler() : this.runIfDirty();
  }
  /**
   * @internal
   */
  runIfDirty() {
    as(this) && this.run();
  }
  get dirty() {
    return as(this);
  }
}
let Yi = 0, fo, po;
function qi(e, t = !1) {
  if (e.flags |= 8, t) {
    e.next = po, po = e;
    return;
  }
  e.next = fo, fo = e;
}
function Ns() {
  Yi++;
}
function zs() {
  if (--Yi > 0)
    return;
  if (po) {
    let t = po;
    for (po = void 0; t; ) {
      const n = t.next;
      t.next = void 0, t.flags &= -9, t = n;
    }
  }
  let e;
  for (; fo; ) {
    let t = fo;
    for (fo = void 0; t; ) {
      const n = t.next;
      if (t.next = void 0, t.flags &= -9, t.flags & 1)
        try {
          t.trigger();
        } catch (o) {
          e || (e = o);
        }
      t = n;
    }
  }
  if (e) throw e;
}
function Xi(e) {
  for (let t = e.deps; t; t = t.nextDep)
    t.version = -1, t.prevActiveLink = t.dep.activeLink, t.dep.activeLink = t;
}
function Ji(e) {
  let t, n = e.depsTail, o = n;
  for (; o; ) {
    const r = o.prevDep;
    o.version === -1 ? (o === n && (n = r), js(o), kc(o)) : t = o, o.dep.activeLink = o.prevActiveLink, o.prevActiveLink = void 0, o = r;
  }
  e.deps = t, e.depsTail = n;
}
function as(e) {
  for (let t = e.deps; t; t = t.nextDep)
    if (t.dep.version !== t.version || t.dep.computed && (Zi(t.dep.computed) || t.dep.version !== t.version))
      return !0;
  return !!e._dirty;
}
function Zi(e) {
  if (e.flags & 4 && !(e.flags & 16) || (e.flags &= -17, e.globalVersion === vo))
    return;
  e.globalVersion = vo;
  const t = e.dep;
  if (e.flags |= 2, t.version > 0 && !e.isSSR && e.deps && !as(e)) {
    e.flags &= -3;
    return;
  }
  const n = Oe, o = Pt;
  Oe = e, Pt = !0;
  try {
    Xi(e);
    const r = e.fn(e._value);
    (t.version === 0 || hn(r, e._value)) && (e._value = r, t.version++);
  } catch (r) {
    throw t.version++, r;
  } finally {
    Oe = n, Pt = o, Ji(e), e.flags &= -3;
  }
}
function js(e, t = !1) {
  const { dep: n, prevSub: o, nextSub: r } = e;
  if (o && (o.nextSub = r, e.prevSub = void 0), r && (r.prevSub = o, e.nextSub = void 0), n.subs === e && (n.subs = o, !o && n.computed)) {
    n.computed.flags &= -5;
    for (let s = n.computed.deps; s; s = s.nextDep)
      js(s, !0);
  }
  !t && !--n.sc && n.map && n.map.delete(n.key);
}
function kc(e) {
  const { prevDep: t, nextDep: n } = e;
  t && (t.nextDep = n, e.prevDep = void 0), n && (n.prevDep = t, e.nextDep = void 0);
}
let Pt = !0;
const Qi = [];
function bn() {
  Qi.push(Pt), Pt = !1;
}
function wn() {
  const e = Qi.pop();
  Pt = e === void 0 ? !0 : e;
}
function Aa(e) {
  const { cleanup: t } = e;
  if (e.cleanup = void 0, t) {
    const n = Oe;
    Oe = void 0;
    try {
      t();
    } finally {
      Oe = n;
    }
  }
}
let vo = 0;
class Mc {
  constructor(t, n) {
    this.sub = t, this.dep = n, this.version = n.version, this.nextDep = this.prevDep = this.nextSub = this.prevSub = this.prevActiveLink = void 0;
  }
}
class mr {
  constructor(t) {
    this.computed = t, this.version = 0, this.activeLink = void 0, this.subs = void 0, this.map = void 0, this.key = void 0, this.sc = 0;
  }
  track(t) {
    if (!Oe || !Pt || Oe === this.computed)
      return;
    let n = this.activeLink;
    if (n === void 0 || n.sub !== Oe)
      n = this.activeLink = new Mc(Oe, this), Oe.deps ? (n.prevDep = Oe.depsTail, Oe.depsTail.nextDep = n, Oe.depsTail = n) : Oe.deps = Oe.depsTail = n, el(n);
    else if (n.version === -1 && (n.version = this.version, n.nextDep)) {
      const o = n.nextDep;
      o.prevDep = n.prevDep, n.prevDep && (n.prevDep.nextDep = o), n.prevDep = Oe.depsTail, n.nextDep = void 0, Oe.depsTail.nextDep = n, Oe.depsTail = n, Oe.deps === n && (Oe.deps = o);
    }
    return n;
  }
  trigger(t) {
    this.version++, vo++, this.notify(t);
  }
  notify(t) {
    Ns();
    try {
      for (let n = this.subs; n; n = n.prevSub)
        n.sub.notify() && n.sub.dep.notify();
    } finally {
      zs();
    }
  }
}
function el(e) {
  if (e.dep.sc++, e.sub.flags & 4) {
    const t = e.dep.computed;
    if (t && !e.dep.subs) {
      t.flags |= 20;
      for (let o = t.deps; o; o = o.nextDep)
        el(o);
    }
    const n = e.dep.subs;
    n !== e && (e.prevSub = n, n && (n.nextSub = e)), e.dep.subs = e;
  }
}
const Zo = /* @__PURE__ */ new WeakMap(), Sn = Symbol(
  ""
), is = Symbol(
  ""
), yo = Symbol(
  ""
);
function et(e, t, n) {
  if (Pt && Oe) {
    let o = Zo.get(e);
    o || Zo.set(e, o = /* @__PURE__ */ new Map());
    let r = o.get(n);
    r || (o.set(n, r = new mr()), r.map = o, r.key = n), r.track();
  }
}
function Xt(e, t, n, o, r, s) {
  const a = Zo.get(e);
  if (!a) {
    vo++;
    return;
  }
  const i = (l) => {
    l && l.trigger();
  };
  if (Ns(), t === "clear")
    a.forEach(i);
  else {
    const l = re(e), u = l && Fs(n);
    if (l && n === "length") {
      const c = Number(o);
      a.forEach((f, p) => {
        (p === "length" || p === yo || !tn(p) && p >= c) && i(f);
      });
    } else
      switch ((n !== void 0 || a.has(void 0)) && i(a.get(n)), u && i(a.get(yo)), t) {
        case "add":
          l ? u && i(a.get("length")) : (i(a.get(Sn)), jn(e) && i(a.get(is)));
          break;
        case "delete":
          l || (i(a.get(Sn)), jn(e) && i(a.get(is)));
          break;
        case "set":
          jn(e) && i(a.get(Sn));
          break;
      }
  }
  zs();
}
function Dc(e, t) {
  const n = Zo.get(e);
  return n && n.get(t);
}
function Dn(e) {
  const t = xe(e);
  return t === e ? t : (et(t, "iterate", yo), Ct(e) ? t : t.map(tt));
}
function gr(e) {
  return et(e = xe(e), "iterate", yo), e;
}
const Rc = {
  __proto__: null,
  [Symbol.iterator]() {
    return Hr(this, Symbol.iterator, tt);
  },
  concat(...e) {
    return Dn(this).concat(
      ...e.map((t) => re(t) ? Dn(t) : t)
    );
  },
  entries() {
    return Hr(this, "entries", (e) => (e[1] = tt(e[1]), e));
  },
  every(e, t) {
    return Ut(this, "every", e, t, void 0, arguments);
  },
  filter(e, t) {
    return Ut(this, "filter", e, t, (n) => n.map(tt), arguments);
  },
  find(e, t) {
    return Ut(this, "find", e, t, tt, arguments);
  },
  findIndex(e, t) {
    return Ut(this, "findIndex", e, t, void 0, arguments);
  },
  findLast(e, t) {
    return Ut(this, "findLast", e, t, tt, arguments);
  },
  findLastIndex(e, t) {
    return Ut(this, "findLastIndex", e, t, void 0, arguments);
  },
  // flat, flatMap could benefit from ARRAY_ITERATE but are not straight-forward to implement
  forEach(e, t) {
    return Ut(this, "forEach", e, t, void 0, arguments);
  },
  includes(...e) {
    return Wr(this, "includes", e);
  },
  indexOf(...e) {
    return Wr(this, "indexOf", e);
  },
  join(e) {
    return Dn(this).join(e);
  },
  // keys() iterator only reads `length`, no optimisation required
  lastIndexOf(...e) {
    return Wr(this, "lastIndexOf", e);
  },
  map(e, t) {
    return Ut(this, "map", e, t, void 0, arguments);
  },
  pop() {
    return ro(this, "pop");
  },
  push(...e) {
    return ro(this, "push", e);
  },
  reduce(e, ...t) {
    return Oa(this, "reduce", e, t);
  },
  reduceRight(e, ...t) {
    return Oa(this, "reduceRight", e, t);
  },
  shift() {
    return ro(this, "shift");
  },
  // slice could use ARRAY_ITERATE but also seems to beg for range tracking
  some(e, t) {
    return Ut(this, "some", e, t, void 0, arguments);
  },
  splice(...e) {
    return ro(this, "splice", e);
  },
  toReversed() {
    return Dn(this).toReversed();
  },
  toSorted(e) {
    return Dn(this).toSorted(e);
  },
  toSpliced(...e) {
    return Dn(this).toSpliced(...e);
  },
  unshift(...e) {
    return ro(this, "unshift", e);
  },
  values() {
    return Hr(this, "values", tt);
  }
};
function Hr(e, t, n) {
  const o = gr(e), r = o[t]();
  return o !== e && !Ct(e) && (r._next = r.next, r.next = () => {
    const s = r._next();
    return s.value && (s.value = n(s.value)), s;
  }), r;
}
const Ic = Array.prototype;
function Ut(e, t, n, o, r, s) {
  const a = gr(e), i = a !== e && !Ct(e), l = a[t];
  if (l !== Ic[t]) {
    const f = l.apply(e, s);
    return i ? tt(f) : f;
  }
  let u = n;
  a !== e && (i ? u = function(f, p) {
    return n.call(this, tt(f), p, e);
  } : n.length > 2 && (u = function(f, p) {
    return n.call(this, f, p, e);
  }));
  const c = l.call(a, u, o);
  return i && r ? r(c) : c;
}
function Oa(e, t, n, o) {
  const r = gr(e);
  let s = n;
  return r !== e && (Ct(e) ? n.length > 3 && (s = function(a, i, l) {
    return n.call(this, a, i, l, e);
  }) : s = function(a, i, l) {
    return n.call(this, a, tt(i), l, e);
  }), r[t](s, ...o);
}
function Wr(e, t, n) {
  const o = xe(e);
  et(o, "iterate", yo);
  const r = o[t](...n);
  return (r === -1 || r === !1) && Hs(n[0]) ? (n[0] = xe(n[0]), o[t](...n)) : r;
}
function ro(e, t, n = []) {
  bn(), Ns();
  const o = xe(e)[t].apply(e, n);
  return zs(), wn(), o;
}
const Lc = /* @__PURE__ */ Rs("__proto__,__v_isRef,__isVue"), tl = new Set(
  /* @__PURE__ */ Object.getOwnPropertyNames(Symbol).filter((e) => e !== "arguments" && e !== "caller").map((e) => Symbol[e]).filter(tn)
);
function Fc(e) {
  tn(e) || (e = String(e));
  const t = xe(this);
  return et(t, "has", e), t.hasOwnProperty(e);
}
class nl {
  constructor(t = !1, n = !1) {
    this._isReadonly = t, this._isShallow = n;
  }
  get(t, n, o) {
    if (n === "__v_skip") return t.__v_skip;
    const r = this._isReadonly, s = this._isShallow;
    if (n === "__v_isReactive")
      return !r;
    if (n === "__v_isReadonly")
      return r;
    if (n === "__v_isShallow")
      return s;
    if (n === "__v_raw")
      return o === (r ? s ? ll : il : s ? al : sl).get(t) || // receiver is not the reactive proxy, but has the same prototype
      // this means the receiver is a user proxy of the reactive proxy
      Object.getPrototypeOf(t) === Object.getPrototypeOf(o) ? t : void 0;
    const a = re(t);
    if (!r) {
      let l;
      if (a && (l = Rc[n]))
        return l;
      if (n === "hasOwnProperty")
        return Fc;
    }
    const i = Reflect.get(
      t,
      n,
      // if this is a proxy wrapping a ref, return methods using the raw ref
      // as receiver so that we don't have to call `toRaw` on the ref in all
      // its class methods
      He(t) ? t : o
    );
    return (tn(n) ? tl.has(n) : Lc(n)) || (r || et(t, "get", n), s) ? i : He(i) ? a && Fs(n) ? i : i.value : Ie(i) ? r ? yr(i) : Po(i) : i;
  }
}
class ol extends nl {
  constructor(t = !1) {
    super(!1, t);
  }
  set(t, n, o, r) {
    let s = t[n];
    if (!this._isShallow) {
      const l = $n(s);
      if (!Ct(o) && !$n(o) && (s = xe(s), o = xe(o)), !re(t) && He(s) && !He(o))
        return l ? !1 : (s.value = o, !0);
    }
    const a = re(t) && Fs(n) ? Number(n) < t.length : Ce(t, n), i = Reflect.set(
      t,
      n,
      o,
      He(t) ? t : r
    );
    return t === xe(r) && (a ? hn(o, s) && Xt(t, "set", n, o) : Xt(t, "add", n, o)), i;
  }
  deleteProperty(t, n) {
    const o = Ce(t, n);
    t[n];
    const r = Reflect.deleteProperty(t, n);
    return r && o && Xt(t, "delete", n, void 0), r;
  }
  has(t, n) {
    const o = Reflect.has(t, n);
    return (!tn(n) || !tl.has(n)) && et(t, "has", n), o;
  }
  ownKeys(t) {
    return et(
      t,
      "iterate",
      re(t) ? "length" : Sn
    ), Reflect.ownKeys(t);
  }
}
class rl extends nl {
  constructor(t = !1) {
    super(!0, t);
  }
  set(t, n) {
    return !0;
  }
  deleteProperty(t, n) {
    return !0;
  }
}
const Vc = /* @__PURE__ */ new ol(), Nc = /* @__PURE__ */ new rl(), zc = /* @__PURE__ */ new ol(!0), jc = /* @__PURE__ */ new rl(!0), ls = (e) => e, Ro = (e) => Reflect.getPrototypeOf(e);
function Hc(e, t, n) {
  return function(...o) {
    const r = this.__v_raw, s = xe(r), a = jn(s), i = e === "entries" || e === Symbol.iterator && a, l = e === "keys" && a, u = r[e](...o), c = n ? ls : t ? us : tt;
    return !t && et(
      s,
      "iterate",
      l ? is : Sn
    ), {
      // iterator protocol
      next() {
        const { value: f, done: p } = u.next();
        return p ? { value: f, done: p } : {
          value: i ? [c(f[0]), c(f[1])] : c(f),
          done: p
        };
      },
      // iterable protocol
      [Symbol.iterator]() {
        return this;
      }
    };
  };
}
function Io(e) {
  return function(...t) {
    return e === "delete" ? !1 : e === "clear" ? void 0 : this;
  };
}
function Wc(e, t) {
  const n = {
    get(r) {
      const s = this.__v_raw, a = xe(s), i = xe(r);
      e || (hn(r, i) && et(a, "get", r), et(a, "get", i));
      const { has: l } = Ro(a), u = t ? ls : e ? us : tt;
      if (l.call(a, r))
        return u(s.get(r));
      if (l.call(a, i))
        return u(s.get(i));
      s !== a && s.get(r);
    },
    get size() {
      const r = this.__v_raw;
      return !e && et(xe(r), "iterate", Sn), Reflect.get(r, "size", r);
    },
    has(r) {
      const s = this.__v_raw, a = xe(s), i = xe(r);
      return e || (hn(r, i) && et(a, "has", r), et(a, "has", i)), r === i ? s.has(r) : s.has(r) || s.has(i);
    },
    forEach(r, s) {
      const a = this, i = a.__v_raw, l = xe(i), u = t ? ls : e ? us : tt;
      return !e && et(l, "iterate", Sn), i.forEach((c, f) => r.call(s, u(c), u(f), a));
    }
  };
  return Ke(
    n,
    e ? {
      add: Io("add"),
      set: Io("set"),
      delete: Io("delete"),
      clear: Io("clear")
    } : {
      add(r) {
        !t && !Ct(r) && !$n(r) && (r = xe(r));
        const s = xe(this);
        return Ro(s).has.call(s, r) || (s.add(r), Xt(s, "add", r, r)), this;
      },
      set(r, s) {
        !t && !Ct(s) && !$n(s) && (s = xe(s));
        const a = xe(this), { has: i, get: l } = Ro(a);
        let u = i.call(a, r);
        u || (r = xe(r), u = i.call(a, r));
        const c = l.call(a, r);
        return a.set(r, s), u ? hn(s, c) && Xt(a, "set", r, s) : Xt(a, "add", r, s), this;
      },
      delete(r) {
        const s = xe(this), { has: a, get: i } = Ro(s);
        let l = a.call(s, r);
        l || (r = xe(r), l = a.call(s, r)), i && i.call(s, r);
        const u = s.delete(r);
        return l && Xt(s, "delete", r, void 0), u;
      },
      clear() {
        const r = xe(this), s = r.size !== 0, a = r.clear();
        return s && Xt(
          r,
          "clear",
          void 0,
          void 0
        ), a;
      }
    }
  ), [
    "keys",
    "values",
    "entries",
    Symbol.iterator
  ].forEach((r) => {
    n[r] = Hc(r, e, t);
  }), n;
}
function vr(e, t) {
  const n = Wc(e, t);
  return (o, r, s) => r === "__v_isReactive" ? !e : r === "__v_isReadonly" ? e : r === "__v_raw" ? o : Reflect.get(
    Ce(n, r) && r in o ? n : o,
    r,
    s
  );
}
const Kc = {
  get: /* @__PURE__ */ vr(!1, !1)
}, Uc = {
  get: /* @__PURE__ */ vr(!1, !0)
}, Gc = {
  get: /* @__PURE__ */ vr(!0, !1)
}, Yc = {
  get: /* @__PURE__ */ vr(!0, !0)
}, sl = /* @__PURE__ */ new WeakMap(), al = /* @__PURE__ */ new WeakMap(), il = /* @__PURE__ */ new WeakMap(), ll = /* @__PURE__ */ new WeakMap();
function qc(e) {
  switch (e) {
    case "Object":
    case "Array":
      return 1;
    case "Map":
    case "Set":
    case "WeakMap":
    case "WeakSet":
      return 2;
    default:
      return 0;
  }
}
function Xc(e) {
  return e.__v_skip || !Object.isExtensible(e) ? 0 : qc(Cc(e));
}
function Po(e) {
  return $n(e) ? e : br(
    e,
    !1,
    Vc,
    Kc,
    sl
  );
}
function Jc(e) {
  return br(
    e,
    !1,
    zc,
    Uc,
    al
  );
}
function yr(e) {
  return br(
    e,
    !0,
    Nc,
    Gc,
    il
  );
}
function Rn(e) {
  return br(
    e,
    !0,
    jc,
    Yc,
    ll
  );
}
function br(e, t, n, o, r) {
  if (!Ie(e) || e.__v_raw && !(t && e.__v_isReactive))
    return e;
  const s = r.get(e);
  if (s)
    return s;
  const a = Xc(e);
  if (a === 0)
    return e;
  const i = new Proxy(
    e,
    a === 2 ? o : n
  );
  return r.set(e, i), i;
}
function Hn(e) {
  return $n(e) ? Hn(e.__v_raw) : !!(e && e.__v_isReactive);
}
function $n(e) {
  return !!(e && e.__v_isReadonly);
}
function Ct(e) {
  return !!(e && e.__v_isShallow);
}
function Hs(e) {
  return e ? !!e.__v_raw : !1;
}
function xe(e) {
  const t = e && e.__v_raw;
  return t ? xe(t) : e;
}
function ul(e) {
  return !Ce(e, "__v_skip") && Object.isExtensible(e) && Ni(e, "__v_skip", !0), e;
}
const tt = (e) => Ie(e) ? Po(e) : e, us = (e) => Ie(e) ? yr(e) : e;
function He(e) {
  return e ? e.__v_isRef === !0 : !1;
}
function B(e) {
  return cl(e, !1);
}
function wr(e) {
  return cl(e, !0);
}
function cl(e, t) {
  return He(e) ? e : new Zc(e, t);
}
class Zc {
  constructor(t, n) {
    this.dep = new mr(), this.__v_isRef = !0, this.__v_isShallow = !1, this._rawValue = n ? t : xe(t), this._value = n ? t : tt(t), this.__v_isShallow = n;
  }
  get value() {
    return this.dep.track(), this._value;
  }
  set value(t) {
    const n = this._rawValue, o = this.__v_isShallow || Ct(t) || $n(t);
    t = o ? t : xe(t), hn(t, n) && (this._rawValue = t, this._value = o ? t : tt(t), this.dep.trigger());
  }
}
function d(e) {
  return He(e) ? e.value : e;
}
function mt(e) {
  return ae(e) ? e() : d(e);
}
const Qc = {
  get: (e, t, n) => t === "__v_raw" ? e : d(Reflect.get(e, t, n)),
  set: (e, t, n, o) => {
    const r = e[t];
    return He(r) && !He(n) ? (r.value = n, !0) : Reflect.set(e, t, n, o);
  }
};
function dl(e) {
  return Hn(e) ? e : new Proxy(e, Qc);
}
class ed {
  constructor(t) {
    this.__v_isRef = !0, this._value = void 0;
    const n = this.dep = new mr(), { get: o, set: r } = t(n.track.bind(n), n.trigger.bind(n));
    this._get = o, this._set = r;
  }
  get value() {
    return this._value = this._get();
  }
  set value(t) {
    this._set(t);
  }
}
function td(e) {
  return new ed(e);
}
function Xe(e) {
  const t = re(e) ? new Array(e.length) : {};
  for (const n in e)
    t[n] = fl(e, n);
  return t;
}
class nd {
  constructor(t, n, o) {
    this._object = t, this._key = n, this._defaultValue = o, this.__v_isRef = !0, this._value = void 0;
  }
  get value() {
    const t = this._object[this._key];
    return this._value = t === void 0 ? this._defaultValue : t;
  }
  set value(t) {
    this._object[this._key] = t;
  }
  get dep() {
    return Dc(xe(this._object), this._key);
  }
}
class od {
  constructor(t) {
    this._getter = t, this.__v_isRef = !0, this.__v_isReadonly = !0, this._value = void 0;
  }
  get value() {
    return this._value = this._getter();
  }
}
function rd(e, t, n) {
  return He(e) ? e : ae(e) ? new od(e) : Ie(e) && arguments.length > 1 ? fl(e, t, n) : B(e);
}
function fl(e, t, n) {
  const o = e[t];
  return He(o) ? o : new nd(e, t, n);
}
class sd {
  constructor(t, n, o) {
    this.fn = t, this.setter = n, this._value = void 0, this.dep = new mr(this), this.__v_isRef = !0, this.deps = void 0, this.depsTail = void 0, this.flags = 16, this.globalVersion = vo - 1, this.next = void 0, this.effect = this, this.__v_isReadonly = !n, this.isSSR = o;
  }
  /**
   * @internal
   */
  notify() {
    if (this.flags |= 16, !(this.flags & 8) && // avoid infinite self recursion
    Oe !== this)
      return qi(this, !0), !0;
  }
  get value() {
    const t = this.dep.track();
    return Zi(this), t && (t.version = this.dep.version), this._value;
  }
  set value(t) {
    this.setter && this.setter(t);
  }
}
function ad(e, t, n = !1) {
  let o, r;
  return ae(e) ? o = e : (o = e.get, r = e.set), new sd(o, r, n);
}
const Lo = {}, Qo = /* @__PURE__ */ new WeakMap();
let Cn;
function id(e, t = !1, n = Cn) {
  if (n) {
    let o = Qo.get(n);
    o || Qo.set(n, o = []), o.push(e);
  }
}
function ld(e, t, n = Ae) {
  const { immediate: o, deep: r, once: s, scheduler: a, augmentJob: i, call: l } = n, u = (x) => r ? x : Ct(x) || r === !1 || r === 0 ? Jt(x, 1) : Jt(x);
  let c, f, p, h, g = !1, y = !1;
  if (He(e) ? (f = () => e.value, g = Ct(e)) : Hn(e) ? (f = () => u(e), g = !0) : re(e) ? (y = !0, g = e.some((x) => Hn(x) || Ct(x)), f = () => e.map((x) => {
    if (He(x))
      return x.value;
    if (Hn(x))
      return u(x);
    if (ae(x))
      return l ? l(x, 2) : x();
  })) : ae(e) ? t ? f = l ? () => l(e, 2) : e : f = () => {
    if (p) {
      bn();
      try {
        p();
      } finally {
        wn();
      }
    }
    const x = Cn;
    Cn = c;
    try {
      return l ? l(e, 3, [h]) : e(h);
    } finally {
      Cn = x;
    }
  } : f = It, t && r) {
    const x = f, A = r === !0 ? 1 / 0 : r;
    f = () => Jt(x(), A);
  }
  const C = Vs(), v = () => {
    c.stop(), C && C.active && Ls(C.effects, c);
  };
  if (s && t) {
    const x = t;
    t = (...A) => {
      x(...A), v();
    };
  }
  let w = y ? new Array(e.length).fill(Lo) : Lo;
  const m = (x) => {
    if (!(!(c.flags & 1) || !c.dirty && !x))
      if (t) {
        const A = c.run();
        if (r || g || (y ? A.some((R, I) => hn(R, w[I])) : hn(A, w))) {
          p && p();
          const R = Cn;
          Cn = c;
          try {
            const I = [
              A,
              // pass undefined as the old value when it's changed for the first time
              w === Lo ? void 0 : y && w[0] === Lo ? [] : w,
              h
            ];
            l ? l(t, 3, I) : (
              // @ts-expect-error
              t(...I)
            ), w = A;
          } finally {
            Cn = R;
          }
        }
      } else
        c.run();
  };
  return i && i(m), c = new Gi(f), c.scheduler = a ? () => a(m, !1) : m, h = (x) => id(x, !1, c), p = c.onStop = () => {
    const x = Qo.get(c);
    if (x) {
      if (l)
        l(x, 4);
      else
        for (const A of x) A();
      Qo.delete(c);
    }
  }, t ? o ? m(!0) : w = c.run() : a ? a(m.bind(null, !0), !0) : c.run(), v.pause = c.pause.bind(c), v.resume = c.resume.bind(c), v.stop = v, v;
}
function Jt(e, t = 1 / 0, n) {
  if (t <= 0 || !Ie(e) || e.__v_skip || (n = n || /* @__PURE__ */ new Set(), n.has(e)))
    return e;
  if (n.add(e), t--, He(e))
    Jt(e.value, t, n);
  else if (re(e))
    for (let o = 0; o < e.length; o++)
      Jt(e[o], t, n);
  else if (Li(e) || jn(e))
    e.forEach((o) => {
      Jt(o, t, n);
    });
  else if (dr(e)) {
    for (const o in e)
      Jt(e[o], t, n);
    for (const o of Object.getOwnPropertySymbols(e))
      Object.prototype.propertyIsEnumerable.call(e, o) && Jt(e[o], t, n);
  }
  return e;
}
/**
* @vue/runtime-core v3.5.13
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
function Eo(e, t, n, o) {
  try {
    return o ? e(...o) : e();
  } catch (r) {
    _r(r, t, n);
  }
}
function Ft(e, t, n, o) {
  if (ae(e)) {
    const r = Eo(e, t, n, o);
    return r && Fi(r) && r.catch((s) => {
      _r(s, t, n);
    }), r;
  }
  if (re(e)) {
    const r = [];
    for (let s = 0; s < e.length; s++)
      r.push(Ft(e[s], t, n, o));
    return r;
  }
}
function _r(e, t, n, o = !0) {
  const r = t ? t.vnode : null, { errorHandler: s, throwUnhandledErrorInProduction: a } = t && t.appContext.config || Ae;
  if (t) {
    let i = t.parent;
    const l = t.proxy, u = `https://vuejs.org/error-reference/#runtime-${n}`;
    for (; i; ) {
      const c = i.ec;
      if (c) {
        for (let f = 0; f < c.length; f++)
          if (c[f](e, l, u) === !1)
            return;
      }
      i = i.parent;
    }
    if (s) {
      bn(), Eo(s, null, 10, [
        e,
        l,
        u
      ]), wn();
      return;
    }
  }
  ud(e, n, r, o, a);
}
function ud(e, t, n, o = !0, r = !1) {
  if (r)
    throw e;
  console.error(e);
}
const lt = [];
let Dt = -1;
const Wn = [];
let fn = null, Fn = 0;
const pl = /* @__PURE__ */ Promise.resolve();
let er = null;
function Le(e) {
  const t = er || pl;
  return e ? t.then(this ? e.bind(this) : e) : t;
}
function cd(e) {
  let t = Dt + 1, n = lt.length;
  for (; t < n; ) {
    const o = t + n >>> 1, r = lt[o], s = bo(r);
    s < e || s === e && r.flags & 2 ? t = o + 1 : n = o;
  }
  return t;
}
function Ws(e) {
  if (!(e.flags & 1)) {
    const t = bo(e), n = lt[lt.length - 1];
    !n || // fast path when the job id is larger than the tail
    !(e.flags & 2) && t >= bo(n) ? lt.push(e) : lt.splice(cd(t), 0, e), e.flags |= 1, hl();
  }
}
function hl() {
  er || (er = pl.then(gl));
}
function dd(e) {
  re(e) ? Wn.push(...e) : fn && e.id === -1 ? fn.splice(Fn + 1, 0, e) : e.flags & 1 || (Wn.push(e), e.flags |= 1), hl();
}
function Ba(e, t, n = Dt + 1) {
  for (; n < lt.length; n++) {
    const o = lt[n];
    if (o && o.flags & 2) {
      if (e && o.id !== e.uid)
        continue;
      lt.splice(n, 1), n--, o.flags & 4 && (o.flags &= -2), o(), o.flags & 4 || (o.flags &= -2);
    }
  }
}
function ml(e) {
  if (Wn.length) {
    const t = [...new Set(Wn)].sort(
      (n, o) => bo(n) - bo(o)
    );
    if (Wn.length = 0, fn) {
      fn.push(...t);
      return;
    }
    for (fn = t, Fn = 0; Fn < fn.length; Fn++) {
      const n = fn[Fn];
      n.flags & 4 && (n.flags &= -2), n.flags & 8 || n(), n.flags &= -2;
    }
    fn = null, Fn = 0;
  }
}
const bo = (e) => e.id == null ? e.flags & 2 ? -1 : 1 / 0 : e.id;
function gl(e) {
  try {
    for (Dt = 0; Dt < lt.length; Dt++) {
      const t = lt[Dt];
      t && !(t.flags & 8) && (t.flags & 4 && (t.flags &= -2), Eo(
        t,
        t.i,
        t.i ? 15 : 14
      ), t.flags & 4 || (t.flags &= -2));
    }
  } finally {
    for (; Dt < lt.length; Dt++) {
      const t = lt[Dt];
      t && (t.flags &= -2);
    }
    Dt = -1, lt.length = 0, ml(), er = null, (lt.length || Wn.length) && gl();
  }
}
let Ue = null, vl = null;
function tr(e) {
  const t = Ue;
  return Ue = e, vl = e && e.type.__scopeId || null, t;
}
function S(e, t = Ue, n) {
  if (!t || e._n)
    return e;
  const o = (...r) => {
    o._d && Ha(-1);
    const s = tr(t);
    let a;
    try {
      a = e(...r);
    } finally {
      tr(s), o._d && Ha(1);
    }
    return a;
  };
  return o._n = !0, o._c = !0, o._d = !0, o;
}
function fd(e, t) {
  if (Ue === null)
    return e;
  const n = Tr(Ue), o = e.dirs || (e.dirs = []);
  for (let r = 0; r < t.length; r++) {
    let [s, a, i, l = Ae] = t[r];
    s && (ae(s) && (s = {
      mounted: s,
      updated: s
    }), s.deep && Jt(a), o.push({
      dir: s,
      instance: n,
      value: a,
      oldValue: void 0,
      arg: i,
      modifiers: l
    }));
  }
  return e;
}
function _n(e, t, n, o) {
  const r = e.dirs, s = t && t.dirs;
  for (let a = 0; a < r.length; a++) {
    const i = r[a];
    s && (i.oldValue = s[a].value);
    let l = i.dir[o];
    l && (bn(), Ft(l, n, 8, [
      e.el,
      i,
      e,
      t
    ]), wn());
  }
}
const yl = Symbol("_vte"), pd = (e) => e.__isTeleport, ho = (e) => e && (e.disabled || e.disabled === ""), ka = (e) => e && (e.defer || e.defer === ""), Ma = (e) => typeof SVGElement < "u" && e instanceof SVGElement, Da = (e) => typeof MathMLElement == "function" && e instanceof MathMLElement, cs = (e, t) => {
  const n = e && e.to;
  return Fe(n) ? t ? t(n) : null : n;
}, bl = {
  name: "Teleport",
  __isTeleport: !0,
  process(e, t, n, o, r, s, a, i, l, u) {
    const {
      mc: c,
      pc: f,
      pbc: p,
      o: { insert: h, querySelector: g, createText: y, createComment: C }
    } = u, v = ho(t.props);
    let { shapeFlag: w, children: m, dynamicChildren: x } = t;
    if (e == null) {
      const A = t.el = y(""), R = t.anchor = y("");
      h(A, n, o), h(R, n, o);
      const I = (k, j) => {
        w & 16 && (r && r.isCE && (r.ce._teleportTarget = k), c(
          m,
          k,
          j,
          r,
          s,
          a,
          i,
          l
        ));
      }, H = () => {
        const k = t.target = cs(t.props, g), j = _l(k, t, y, h);
        k && (a !== "svg" && Ma(k) ? a = "svg" : a !== "mathml" && Da(k) && (a = "mathml"), v || (I(k, j), Go(t, !1)));
      };
      v && (I(n, R), Go(t, !0)), ka(t.props) ? at(() => {
        H(), t.el.__isMounted = !0;
      }, s) : H();
    } else {
      if (ka(t.props) && !e.el.__isMounted) {
        at(() => {
          bl.process(
            e,
            t,
            n,
            o,
            r,
            s,
            a,
            i,
            l,
            u
          ), delete e.el.__isMounted;
        }, s);
        return;
      }
      t.el = e.el, t.targetStart = e.targetStart;
      const A = t.anchor = e.anchor, R = t.target = e.target, I = t.targetAnchor = e.targetAnchor, H = ho(e.props), k = H ? n : R, j = H ? A : I;
      if (a === "svg" || Ma(R) ? a = "svg" : (a === "mathml" || Da(R)) && (a = "mathml"), x ? (p(
        e.dynamicChildren,
        x,
        k,
        r,
        s,
        a,
        i
      ), Gs(e, t, !0)) : l || f(
        e,
        t,
        k,
        j,
        r,
        s,
        a,
        i,
        !1
      ), v)
        H ? t.props && e.props && t.props.to !== e.props.to && (t.props.to = e.props.to) : Fo(
          t,
          n,
          A,
          u,
          1
        );
      else if ((t.props && t.props.to) !== (e.props && e.props.to)) {
        const K = t.target = cs(
          t.props,
          g
        );
        K && Fo(
          t,
          K,
          null,
          u,
          0
        );
      } else H && Fo(
        t,
        R,
        I,
        u,
        1
      );
      Go(t, v);
    }
  },
  remove(e, t, n, { um: o, o: { remove: r } }, s) {
    const {
      shapeFlag: a,
      children: i,
      anchor: l,
      targetStart: u,
      targetAnchor: c,
      target: f,
      props: p
    } = e;
    if (f && (r(u), r(c)), s && r(l), a & 16) {
      const h = s || !ho(p);
      for (let g = 0; g < i.length; g++) {
        const y = i[g];
        o(
          y,
          t,
          n,
          h,
          !!y.dynamicChildren
        );
      }
    }
  },
  move: Fo,
  hydrate: hd
};
function Fo(e, t, n, { o: { insert: o }, m: r }, s = 2) {
  s === 0 && o(e.targetAnchor, t, n);
  const { el: a, anchor: i, shapeFlag: l, children: u, props: c } = e, f = s === 2;
  if (f && o(a, t, n), (!f || ho(c)) && l & 16)
    for (let p = 0; p < u.length; p++)
      r(
        u[p],
        t,
        n,
        2
      );
  f && o(i, t, n);
}
function hd(e, t, n, o, r, s, {
  o: { nextSibling: a, parentNode: i, querySelector: l, insert: u, createText: c }
}, f) {
  const p = t.target = cs(
    t.props,
    l
  );
  if (p) {
    const h = ho(t.props), g = p._lpa || p.firstChild;
    if (t.shapeFlag & 16)
      if (h)
        t.anchor = f(
          a(e),
          t,
          i(e),
          n,
          o,
          r,
          s
        ), t.targetStart = g, t.targetAnchor = g && a(g);
      else {
        t.anchor = a(e);
        let y = g;
        for (; y; ) {
          if (y && y.nodeType === 8) {
            if (y.data === "teleport start anchor")
              t.targetStart = y;
            else if (y.data === "teleport anchor") {
              t.targetAnchor = y, p._lpa = t.targetAnchor && a(t.targetAnchor);
              break;
            }
          }
          y = a(y);
        }
        t.targetAnchor || _l(p, t, c, u), f(
          g && a(g),
          t,
          p,
          n,
          o,
          r,
          s
        );
      }
    Go(t, h);
  }
  return t.anchor && a(t.anchor);
}
const wl = bl;
function Go(e, t) {
  const n = e.ctx;
  if (n && n.ut) {
    let o, r;
    for (t ? (o = e.el, r = e.anchor) : (o = e.targetStart, r = e.targetAnchor); o && o !== r; )
      o.nodeType === 1 && o.setAttribute("data-v-owner", n.uid), o = o.nextSibling;
    n.ut();
  }
}
function _l(e, t, n, o) {
  const r = t.targetStart = n(""), s = t.targetAnchor = n("");
  return r[yl] = s, e && (o(r, e), o(s, e)), s;
}
function Ks(e, t) {
  e.shapeFlag & 6 && e.component ? (e.transition = t, Ks(e.component.subTree, t)) : e.shapeFlag & 128 ? (e.ssContent.transition = t.clone(e.ssContent), e.ssFallback.transition = t.clone(e.ssFallback)) : e.transition = t;
}
/*! #__NO_SIDE_EFFECTS__ */
// @__NO_SIDE_EFFECTS__
function T(e, t) {
  return ae(e) ? (
    // #8236: extend call and options.name access are considered side-effects
    // by Rollup, so we have to wrap it in a pure-annotated IIFE.
    Ke({ name: e.name }, t, { setup: e })
  ) : e;
}
function ds() {
  const e = jt();
  return e ? (e.appContext.config.idPrefix || "v") + "-" + e.ids[0] + e.ids[1]++ : "";
}
function xl(e) {
  e.ids = [e.ids[0] + e.ids[2]++ + "-", 0, 0];
}
function nr(e, t, n, o, r = !1) {
  if (re(e)) {
    e.forEach(
      (g, y) => nr(
        g,
        t && (re(t) ? t[y] : t),
        n,
        o,
        r
      )
    );
    return;
  }
  if (Kn(o) && !r) {
    o.shapeFlag & 512 && o.type.__asyncResolved && o.component.subTree.component && nr(e, t, n, o.component.subTree);
    return;
  }
  const s = o.shapeFlag & 4 ? Tr(o.component) : o.el, a = r ? null : s, { i, r: l } = e, u = t && t.r, c = i.refs === Ae ? i.refs = {} : i.refs, f = i.setupState, p = xe(f), h = f === Ae ? () => !1 : (g) => Ce(p, g);
  if (u != null && u !== l && (Fe(u) ? (c[u] = null, h(u) && (f[u] = null)) : He(u) && (u.value = null)), ae(l))
    Eo(l, i, 12, [a, c]);
  else {
    const g = Fe(l), y = He(l);
    if (g || y) {
      const C = () => {
        if (e.f) {
          const v = g ? h(l) ? f[l] : c[l] : l.value;
          r ? re(v) && Ls(v, s) : re(v) ? v.includes(s) || v.push(s) : g ? (c[l] = [s], h(l) && (f[l] = c[l])) : (l.value = [s], e.k && (c[e.k] = l.value));
        } else g ? (c[l] = a, h(l) && (f[l] = a)) : y && (l.value = a, e.k && (c[e.k] = a));
      };
      a ? (C.id = -1, at(C, n)) : C();
    }
  }
}
hr().requestIdleCallback;
hr().cancelIdleCallback;
const Kn = (e) => !!e.type.__asyncLoader, Cl = (e) => e.type.__isKeepAlive;
function md(e, t) {
  Sl(e, "a", t);
}
function gd(e, t) {
  Sl(e, "da", t);
}
function Sl(e, t, n = Ye) {
  const o = e.__wdc || (e.__wdc = () => {
    let r = n;
    for (; r; ) {
      if (r.isDeactivated)
        return;
      r = r.parent;
    }
    return e();
  });
  if (xr(t, o, n), n) {
    let r = n.parent;
    for (; r && r.parent; )
      Cl(r.parent.vnode) && vd(o, t, n, r), r = r.parent;
  }
}
function vd(e, t, n, o) {
  const r = xr(
    t,
    e,
    o,
    !0
    /* prepend */
  );
  bt(() => {
    Ls(o[t], r);
  }, n);
}
function xr(e, t, n = Ye, o = !1) {
  if (n) {
    const r = n[e] || (n[e] = []), s = t.__weh || (t.__weh = (...a) => {
      bn();
      const i = Oo(n), l = Ft(t, n, e, a);
      return i(), wn(), l;
    });
    return o ? r.unshift(s) : r.push(s), s;
  }
}
const nn = (e) => (t, n = Ye) => {
  (!Co || e === "sp") && xr(e, (...o) => t(...o), n);
}, yd = nn("bm"), Se = nn("m"), bd = nn(
  "bu"
), wd = nn("u"), Ao = nn(
  "bum"
), bt = nn("um"), _d = nn(
  "sp"
), xd = nn("rtg"), Cd = nn("rtc");
function Sd(e, t = Ye) {
  xr("ec", e, t);
}
const $d = "components", $l = Symbol.for("v-ndc");
function _t(e) {
  return Fe(e) ? Td($d, e, !1) || e : e || $l;
}
function Td(e, t, n = !0, o = !1) {
  const r = Ue || Ye;
  if (r) {
    const s = r.type;
    {
      const i = pf(
        s,
        !1
      );
      if (i && (i === t || i === qe(t) || i === pr(qe(t))))
        return s;
    }
    const a = (
      // local registration
      // check instance[type] first which is resolved for options API
      Ra(r[e] || s[e], t) || // global registration
      Ra(r.appContext[e], t)
    );
    return !a && o ? s : a;
  }
}
function Ra(e, t) {
  return e && (e[t] || e[qe(t)] || e[pr(qe(t))]);
}
function wo(e, t, n, o) {
  let r;
  const s = n, a = re(e);
  if (a || Fe(e)) {
    const i = a && Hn(e);
    let l = !1;
    i && (l = !Ct(e), e = gr(e)), r = new Array(e.length);
    for (let u = 0, c = e.length; u < c; u++)
      r[u] = t(
        l ? tt(e[u]) : e[u],
        u,
        void 0,
        s
      );
  } else if (typeof e == "number") {
    r = new Array(e);
    for (let i = 0; i < e; i++)
      r[i] = t(i + 1, i, void 0, s);
  } else if (Ie(e))
    if (e[Symbol.iterator])
      r = Array.from(
        e,
        (i, l) => t(i, l, void 0, s)
      );
    else {
      const i = Object.keys(e);
      r = new Array(i.length);
      for (let l = 0, u = i.length; l < u; l++) {
        const c = i[l];
        r[l] = t(e[c], c, l, s);
      }
    }
  else
    r = [];
  return r;
}
function P(e, t, n = {}, o, r) {
  if (Ue.ce || Ue.parent && Kn(Ue.parent) && Ue.parent.ce)
    return t !== "default" && (n.name = t), _(), E(
      ke,
      null,
      [W("slot", n, o && o())],
      64
    );
  let s = e[t];
  s && s._c && (s._d = !1), _();
  const a = s && Tl(s(n)), i = n.key || // slot content array of a dynamic conditional slot may have a branch
  // key attached in the `createSlots` helper, respect that
  a && a.key, l = E(
    ke,
    {
      key: (i && !tn(i) ? i : `_${t}`) + // #7256 force differentiate fallback content from actual content
      (!a && o ? "_fb" : "")
    },
    a || (o ? o() : []),
    a && e._ === 1 ? 64 : -2
  );
  return l.scopeId && (l.slotScopeIds = [l.scopeId + "-s"]), s && s._c && (s._d = !0), l;
}
function Tl(e) {
  return e.some((t) => xo(t) ? !(t.type === Vt || t.type === ke && !Tl(t.children)) : !0) ? e : null;
}
function Pd(e, t) {
  const n = {};
  for (const o in e)
    n[co(o)] = e[o];
  return n;
}
const fs = (e) => e ? Yl(e) ? Tr(e) : fs(e.parent) : null, mo = (
  // Move PURE marker to new line to workaround compiler discarding it
  // due to type annotation
  /* @__PURE__ */ Ke(/* @__PURE__ */ Object.create(null), {
    $: (e) => e,
    $el: (e) => e.vnode.el,
    $data: (e) => e.data,
    $props: (e) => e.props,
    $attrs: (e) => e.attrs,
    $slots: (e) => e.slots,
    $refs: (e) => e.refs,
    $parent: (e) => fs(e.parent),
    $root: (e) => fs(e.root),
    $host: (e) => e.ce,
    $emit: (e) => e.emit,
    $options: (e) => Ol(e),
    $forceUpdate: (e) => e.f || (e.f = () => {
      Ws(e.update);
    }),
    $nextTick: (e) => e.n || (e.n = Le.bind(e.proxy)),
    $watch: (e) => Xd.bind(e)
  })
), Kr = (e, t) => e !== Ae && !e.__isScriptSetup && Ce(e, t), Ed = {
  get({ _: e }, t) {
    if (t === "__v_skip")
      return !0;
    const { ctx: n, setupState: o, data: r, props: s, accessCache: a, type: i, appContext: l } = e;
    let u;
    if (t[0] !== "$") {
      const h = a[t];
      if (h !== void 0)
        switch (h) {
          case 1:
            return o[t];
          case 2:
            return r[t];
          case 4:
            return n[t];
          case 3:
            return s[t];
        }
      else {
        if (Kr(o, t))
          return a[t] = 1, o[t];
        if (r !== Ae && Ce(r, t))
          return a[t] = 2, r[t];
        if (
          // only cache other properties when instance has declared (thus stable)
          // props
          (u = e.propsOptions[0]) && Ce(u, t)
        )
          return a[t] = 3, s[t];
        if (n !== Ae && Ce(n, t))
          return a[t] = 4, n[t];
        hs && (a[t] = 0);
      }
    }
    const c = mo[t];
    let f, p;
    if (c)
      return t === "$attrs" && et(e.attrs, "get", ""), c(e);
    if (
      // css module (injected by vue-loader)
      (f = i.__cssModules) && (f = f[t])
    )
      return f;
    if (n !== Ae && Ce(n, t))
      return a[t] = 4, n[t];
    if (
      // global properties
      p = l.config.globalProperties, Ce(p, t)
    )
      return p[t];
  },
  set({ _: e }, t, n) {
    const { data: o, setupState: r, ctx: s } = e;
    return Kr(r, t) ? (r[t] = n, !0) : o !== Ae && Ce(o, t) ? (o[t] = n, !0) : Ce(e.props, t) || t[0] === "$" && t.slice(1) in e ? !1 : (s[t] = n, !0);
  },
  has({
    _: { data: e, setupState: t, accessCache: n, ctx: o, appContext: r, propsOptions: s }
  }, a) {
    let i;
    return !!n[a] || e !== Ae && Ce(e, a) || Kr(t, a) || (i = s[0]) && Ce(i, a) || Ce(o, a) || Ce(mo, a) || Ce(r.config.globalProperties, a);
  },
  defineProperty(e, t, n) {
    return n.get != null ? e._.accessCache[t] = 0 : Ce(n, "value") && this.set(e, t, n.value, null), Reflect.defineProperty(e, t, n);
  }
};
function Ad() {
  return Pl().slots;
}
function Od() {
  return Pl().attrs;
}
function Pl() {
  const e = jt();
  return e.setupContext || (e.setupContext = Xl(e));
}
function ps(e) {
  return re(e) ? e.reduce(
    (t, n) => (t[n] = null, t),
    {}
  ) : e;
}
function El(e, t) {
  const n = ps(e);
  for (const o in t) {
    if (o.startsWith("__skip")) continue;
    let r = n[o];
    r ? re(r) || ae(r) ? r = n[o] = { type: r, default: t[o] } : r.default = t[o] : r === null && (r = n[o] = { default: t[o] }), r && t[`__skip_${o}`] && (r.skipFactory = !0);
  }
  return n;
}
let hs = !0;
function Bd(e) {
  const t = Ol(e), n = e.proxy, o = e.ctx;
  hs = !1, t.beforeCreate && Ia(t.beforeCreate, e, "bc");
  const {
    // state
    data: r,
    computed: s,
    methods: a,
    watch: i,
    provide: l,
    inject: u,
    // lifecycle
    created: c,
    beforeMount: f,
    mounted: p,
    beforeUpdate: h,
    updated: g,
    activated: y,
    deactivated: C,
    beforeDestroy: v,
    beforeUnmount: w,
    destroyed: m,
    unmounted: x,
    render: A,
    renderTracked: R,
    renderTriggered: I,
    errorCaptured: H,
    serverPrefetch: k,
    // public API
    expose: j,
    inheritAttrs: K,
    // assets
    components: J,
    directives: ge,
    filters: ce
  } = t;
  if (u && kd(u, o, null), a)
    for (const ee in a) {
      const ne = a[ee];
      ae(ne) && (o[ee] = ne.bind(n));
    }
  if (r) {
    const ee = r.call(n, n);
    Ie(ee) && (e.data = Po(ee));
  }
  if (hs = !0, s)
    for (const ee in s) {
      const ne = s[ee], he = ae(ne) ? ne.bind(n, n) : ae(ne.get) ? ne.get.bind(n, n) : It, je = !ae(ne) && ae(ne.set) ? ne.set.bind(n) : It, ze = O({
        get: he,
        set: je
      });
      Object.defineProperty(o, ee, {
        enumerable: !0,
        configurable: !0,
        get: () => ze.value,
        set: (Je) => ze.value = Je
      });
    }
  if (i)
    for (const ee in i)
      Al(i[ee], o, n, ee);
  if (l) {
    const ee = ae(l) ? l.call(n) : l;
    Reflect.ownKeys(ee).forEach((ne) => {
      Jn(ne, ee[ne]);
    });
  }
  c && Ia(c, e, "c");
  function ue(ee, ne) {
    re(ne) ? ne.forEach((he) => ee(he.bind(n))) : ne && ee(ne.bind(n));
  }
  if (ue(yd, f), ue(Se, p), ue(bd, h), ue(wd, g), ue(md, y), ue(gd, C), ue(Sd, H), ue(Cd, R), ue(xd, I), ue(Ao, w), ue(bt, x), ue(_d, k), re(j))
    if (j.length) {
      const ee = e.exposed || (e.exposed = {});
      j.forEach((ne) => {
        Object.defineProperty(ee, ne, {
          get: () => n[ne],
          set: (he) => n[ne] = he
        });
      });
    } else e.exposed || (e.exposed = {});
  A && e.render === It && (e.render = A), K != null && (e.inheritAttrs = K), J && (e.components = J), ge && (e.directives = ge), k && xl(e);
}
function kd(e, t, n = It) {
  re(e) && (e = ms(e));
  for (const o in e) {
    const r = e[o];
    let s;
    Ie(r) ? "default" in r ? s = Zt(
      r.from || o,
      r.default,
      !0
    ) : s = Zt(r.from || o) : s = Zt(r), He(s) ? Object.defineProperty(t, o, {
      enumerable: !0,
      configurable: !0,
      get: () => s.value,
      set: (a) => s.value = a
    }) : t[o] = s;
  }
}
function Ia(e, t, n) {
  Ft(
    re(e) ? e.map((o) => o.bind(t.proxy)) : e.bind(t.proxy),
    t,
    n
  );
}
function Al(e, t, n, o) {
  let r = o.includes(".") ? Hl(n, o) : () => n[o];
  if (Fe(e)) {
    const s = t[e];
    ae(s) && be(r, s);
  } else if (ae(e))
    be(r, e.bind(n));
  else if (Ie(e))
    if (re(e))
      e.forEach((s) => Al(s, t, n, o));
    else {
      const s = ae(e.handler) ? e.handler.bind(n) : t[e.handler];
      ae(s) && be(r, s, e);
    }
}
function Ol(e) {
  const t = e.type, { mixins: n, extends: o } = t, {
    mixins: r,
    optionsCache: s,
    config: { optionMergeStrategies: a }
  } = e.appContext, i = s.get(t);
  let l;
  return i ? l = i : !r.length && !n && !o ? l = t : (l = {}, r.length && r.forEach(
    (u) => or(l, u, a, !0)
  ), or(l, t, a)), Ie(t) && s.set(t, l), l;
}
function or(e, t, n, o = !1) {
  const { mixins: r, extends: s } = t;
  s && or(e, s, n, !0), r && r.forEach(
    (a) => or(e, a, n, !0)
  );
  for (const a in t)
    if (!(o && a === "expose")) {
      const i = Md[a] || n && n[a];
      e[a] = i ? i(e[a], t[a]) : t[a];
    }
  return e;
}
const Md = {
  data: La,
  props: Fa,
  emits: Fa,
  // objects
  methods: lo,
  computed: lo,
  // lifecycle
  beforeCreate: st,
  created: st,
  beforeMount: st,
  mounted: st,
  beforeUpdate: st,
  updated: st,
  beforeDestroy: st,
  beforeUnmount: st,
  destroyed: st,
  unmounted: st,
  activated: st,
  deactivated: st,
  errorCaptured: st,
  serverPrefetch: st,
  // assets
  components: lo,
  directives: lo,
  // watch
  watch: Rd,
  // provide / inject
  provide: La,
  inject: Dd
};
function La(e, t) {
  return t ? e ? function() {
    return Ke(
      ae(e) ? e.call(this, this) : e,
      ae(t) ? t.call(this, this) : t
    );
  } : t : e;
}
function Dd(e, t) {
  return lo(ms(e), ms(t));
}
function ms(e) {
  if (re(e)) {
    const t = {};
    for (let n = 0; n < e.length; n++)
      t[e[n]] = e[n];
    return t;
  }
  return e;
}
function st(e, t) {
  return e ? [...new Set([].concat(e, t))] : t;
}
function lo(e, t) {
  return e ? Ke(/* @__PURE__ */ Object.create(null), e, t) : t;
}
function Fa(e, t) {
  return e ? re(e) && re(t) ? [.../* @__PURE__ */ new Set([...e, ...t])] : Ke(
    /* @__PURE__ */ Object.create(null),
    ps(e),
    ps(t ?? {})
  ) : t;
}
function Rd(e, t) {
  if (!e) return t;
  if (!t) return e;
  const n = Ke(/* @__PURE__ */ Object.create(null), e);
  for (const o in t)
    n[o] = st(e[o], t[o]);
  return n;
}
function Bl() {
  return {
    app: null,
    config: {
      isNativeTag: _c,
      performance: !1,
      globalProperties: {},
      optionMergeStrategies: {},
      errorHandler: void 0,
      warnHandler: void 0,
      compilerOptions: {}
    },
    mixins: [],
    components: {},
    directives: {},
    provides: /* @__PURE__ */ Object.create(null),
    optionsCache: /* @__PURE__ */ new WeakMap(),
    propsCache: /* @__PURE__ */ new WeakMap(),
    emitsCache: /* @__PURE__ */ new WeakMap()
  };
}
let Id = 0;
function Ld(e, t) {
  return function(o, r = null) {
    ae(o) || (o = Ke({}, o)), r != null && !Ie(r) && (r = null);
    const s = Bl(), a = /* @__PURE__ */ new WeakSet(), i = [];
    let l = !1;
    const u = s.app = {
      _uid: Id++,
      _component: o,
      _props: r,
      _container: null,
      _context: s,
      _instance: null,
      version: mf,
      get config() {
        return s.config;
      },
      set config(c) {
      },
      use(c, ...f) {
        return a.has(c) || (c && ae(c.install) ? (a.add(c), c.install(u, ...f)) : ae(c) && (a.add(c), c(u, ...f))), u;
      },
      mixin(c) {
        return s.mixins.includes(c) || s.mixins.push(c), u;
      },
      component(c, f) {
        return f ? (s.components[c] = f, u) : s.components[c];
      },
      directive(c, f) {
        return f ? (s.directives[c] = f, u) : s.directives[c];
      },
      mount(c, f, p) {
        if (!l) {
          const h = u._ceVNode || W(o, r);
          return h.appContext = s, p === !0 ? p = "svg" : p === !1 && (p = void 0), e(h, c, p), l = !0, u._container = c, c.__vue_app__ = u, Tr(h.component);
        }
      },
      onUnmount(c) {
        i.push(c);
      },
      unmount() {
        l && (Ft(
          i,
          u._instance,
          16
        ), e(null, u._container), delete u._container.__vue_app__);
      },
      provide(c, f) {
        return s.provides[c] = f, u;
      },
      runWithContext(c) {
        const f = Un;
        Un = u;
        try {
          return c();
        } finally {
          Un = f;
        }
      }
    };
    return u;
  };
}
let Un = null;
function Jn(e, t) {
  if (Ye) {
    let n = Ye.provides;
    const o = Ye.parent && Ye.parent.provides;
    o === n && (n = Ye.provides = Object.create(o)), n[e] = t;
  }
}
function Zt(e, t, n = !1) {
  const o = Ye || Ue;
  if (o || Un) {
    const r = Un ? Un._context.provides : o ? o.parent == null ? o.vnode.appContext && o.vnode.appContext.provides : o.parent.provides : void 0;
    if (r && e in r)
      return r[e];
    if (arguments.length > 1)
      return n && ae(t) ? t.call(o && o.proxy) : t;
  }
}
const kl = {}, Ml = () => Object.create(kl), Dl = (e) => Object.getPrototypeOf(e) === kl;
function Fd(e, t, n, o = !1) {
  const r = {}, s = Ml();
  e.propsDefaults = /* @__PURE__ */ Object.create(null), Rl(e, t, r, s);
  for (const a in e.propsOptions[0])
    a in r || (r[a] = void 0);
  n ? e.props = o ? r : Jc(r) : e.type.props ? e.props = r : e.props = s, e.attrs = s;
}
function Vd(e, t, n, o) {
  const {
    props: r,
    attrs: s,
    vnode: { patchFlag: a }
  } = e, i = xe(r), [l] = e.propsOptions;
  let u = !1;
  if (
    // always force full diff in dev
    // - #1942 if hmr is enabled with sfc component
    // - vite#872 non-sfc component used by sfc component
    (o || a > 0) && !(a & 16)
  ) {
    if (a & 8) {
      const c = e.vnode.dynamicProps;
      for (let f = 0; f < c.length; f++) {
        let p = c[f];
        if (Sr(e.emitsOptions, p))
          continue;
        const h = t[p];
        if (l)
          if (Ce(s, p))
            h !== s[p] && (s[p] = h, u = !0);
          else {
            const g = qe(p);
            r[g] = gs(
              l,
              i,
              g,
              h,
              e,
              !1
            );
          }
        else
          h !== s[p] && (s[p] = h, u = !0);
      }
    }
  } else {
    Rl(e, t, r, s) && (u = !0);
    let c;
    for (const f in i)
      (!t || // for camelCase
      !Ce(t, f) && // it's possible the original props was passed in as kebab-case
      // and converted to camelCase (#955)
      ((c = pt(f)) === f || !Ce(t, c))) && (l ? n && // for camelCase
      (n[f] !== void 0 || // for kebab-case
      n[c] !== void 0) && (r[f] = gs(
        l,
        i,
        f,
        void 0,
        e,
        !0
      )) : delete r[f]);
    if (s !== i)
      for (const f in s)
        (!t || !Ce(t, f)) && (delete s[f], u = !0);
  }
  u && Xt(e.attrs, "set", "");
}
function Rl(e, t, n, o) {
  const [r, s] = e.propsOptions;
  let a = !1, i;
  if (t)
    for (let l in t) {
      if (uo(l))
        continue;
      const u = t[l];
      let c;
      r && Ce(r, c = qe(l)) ? !s || !s.includes(c) ? n[c] = u : (i || (i = {}))[c] = u : Sr(e.emitsOptions, l) || (!(l in o) || u !== o[l]) && (o[l] = u, a = !0);
    }
  if (s) {
    const l = xe(n), u = i || Ae;
    for (let c = 0; c < s.length; c++) {
      const f = s[c];
      n[f] = gs(
        r,
        l,
        f,
        u[f],
        e,
        !Ce(u, f)
      );
    }
  }
  return a;
}
function gs(e, t, n, o, r, s) {
  const a = e[n];
  if (a != null) {
    const i = Ce(a, "default");
    if (i && o === void 0) {
      const l = a.default;
      if (a.type !== Function && !a.skipFactory && ae(l)) {
        const { propsDefaults: u } = r;
        if (n in u)
          o = u[n];
        else {
          const c = Oo(r);
          o = u[n] = l.call(
            null,
            t
          ), c();
        }
      } else
        o = l;
      r.ce && r.ce._setProp(n, o);
    }
    a[
      0
      /* shouldCast */
    ] && (s && !i ? o = !1 : a[
      1
      /* shouldCastTrue */
    ] && (o === "" || o === pt(n)) && (o = !0));
  }
  return o;
}
const Nd = /* @__PURE__ */ new WeakMap();
function Il(e, t, n = !1) {
  const o = n ? Nd : t.propsCache, r = o.get(e);
  if (r)
    return r;
  const s = e.props, a = {}, i = [];
  let l = !1;
  if (!ae(e)) {
    const c = (f) => {
      l = !0;
      const [p, h] = Il(f, t, !0);
      Ke(a, p), h && i.push(...h);
    };
    !n && t.mixins.length && t.mixins.forEach(c), e.extends && c(e.extends), e.mixins && e.mixins.forEach(c);
  }
  if (!s && !l)
    return Ie(e) && o.set(e, zn), zn;
  if (re(s))
    for (let c = 0; c < s.length; c++) {
      const f = qe(s[c]);
      Va(f) && (a[f] = Ae);
    }
  else if (s)
    for (const c in s) {
      const f = qe(c);
      if (Va(f)) {
        const p = s[c], h = a[f] = re(p) || ae(p) ? { type: p } : Ke({}, p), g = h.type;
        let y = !1, C = !0;
        if (re(g))
          for (let v = 0; v < g.length; ++v) {
            const w = g[v], m = ae(w) && w.name;
            if (m === "Boolean") {
              y = !0;
              break;
            } else m === "String" && (C = !1);
          }
        else
          y = ae(g) && g.name === "Boolean";
        h[
          0
          /* shouldCast */
        ] = y, h[
          1
          /* shouldCastTrue */
        ] = C, (y || Ce(h, "default")) && i.push(f);
      }
    }
  const u = [a, i];
  return Ie(e) && o.set(e, u), u;
}
function Va(e) {
  return e[0] !== "$" && !uo(e);
}
const Ll = (e) => e[0] === "_" || e === "$stable", Us = (e) => re(e) ? e.map(Rt) : [Rt(e)], zd = (e, t, n) => {
  if (t._n)
    return t;
  const o = S((...r) => Us(t(...r)), n);
  return o._c = !1, o;
}, Fl = (e, t, n) => {
  const o = e._ctx;
  for (const r in e) {
    if (Ll(r)) continue;
    const s = e[r];
    if (ae(s))
      t[r] = zd(r, s, o);
    else if (s != null) {
      const a = Us(s);
      t[r] = () => a;
    }
  }
}, Vl = (e, t) => {
  const n = Us(t);
  e.slots.default = () => n;
}, Nl = (e, t, n) => {
  for (const o in t)
    (n || o !== "_") && (e[o] = t[o]);
}, jd = (e, t, n) => {
  const o = e.slots = Ml();
  if (e.vnode.shapeFlag & 32) {
    const r = t._;
    r ? (Nl(o, t, n), n && Ni(o, "_", r, !0)) : Fl(t, o);
  } else t && Vl(e, t);
}, Hd = (e, t, n) => {
  const { vnode: o, slots: r } = e;
  let s = !0, a = Ae;
  if (o.shapeFlag & 32) {
    const i = t._;
    i ? n && i === 1 ? s = !1 : Nl(r, t, n) : (s = !t.$stable, Fl(t, r)), a = t;
  } else t && (Vl(e, t), a = { default: 1 });
  if (s)
    for (const i in r)
      !Ll(i) && a[i] == null && delete r[i];
}, at = of;
function Wd(e) {
  return Kd(e);
}
function Kd(e, t) {
  const n = hr();
  n.__VUE__ = !0;
  const {
    insert: o,
    remove: r,
    patchProp: s,
    createElement: a,
    createText: i,
    createComment: l,
    setText: u,
    setElementText: c,
    parentNode: f,
    nextSibling: p,
    setScopeId: h = It,
    insertStaticContent: g
  } = e, y = (b, $, M, V = null, L = null, F = null, Y = void 0, G = null, U = !!$.dynamicChildren) => {
    if (b === $)
      return;
    b && !so(b, $) && (V = _e(b), Je(b, L, F, !0), b = null), $.patchFlag === -2 && (U = !1, $.dynamicChildren = null);
    const { type: N, ref: te, shapeFlag: q } = $;
    switch (N) {
      case $r:
        C(b, $, M, V);
        break;
      case Vt:
        v(b, $, M, V);
        break;
      case Yo:
        b == null && w($, M, V, Y);
        break;
      case ke:
        J(
          b,
          $,
          M,
          V,
          L,
          F,
          Y,
          G,
          U
        );
        break;
      default:
        q & 1 ? A(
          b,
          $,
          M,
          V,
          L,
          F,
          Y,
          G,
          U
        ) : q & 6 ? ge(
          b,
          $,
          M,
          V,
          L,
          F,
          Y,
          G,
          U
        ) : (q & 64 || q & 128) && N.process(
          b,
          $,
          M,
          V,
          L,
          F,
          Y,
          G,
          U,
          De
        );
    }
    te != null && L && nr(te, b && b.ref, F, $ || b, !$);
  }, C = (b, $, M, V) => {
    if (b == null)
      o(
        $.el = i($.children),
        M,
        V
      );
    else {
      const L = $.el = b.el;
      $.children !== b.children && u(L, $.children);
    }
  }, v = (b, $, M, V) => {
    b == null ? o(
      $.el = l($.children || ""),
      M,
      V
    ) : $.el = b.el;
  }, w = (b, $, M, V) => {
    [b.el, b.anchor] = g(
      b.children,
      $,
      M,
      V,
      b.el,
      b.anchor
    );
  }, m = ({ el: b, anchor: $ }, M, V) => {
    let L;
    for (; b && b !== $; )
      L = p(b), o(b, M, V), b = L;
    o($, M, V);
  }, x = ({ el: b, anchor: $ }) => {
    let M;
    for (; b && b !== $; )
      M = p(b), r(b), b = M;
    r($);
  }, A = (b, $, M, V, L, F, Y, G, U) => {
    $.type === "svg" ? Y = "svg" : $.type === "math" && (Y = "mathml"), b == null ? R(
      $,
      M,
      V,
      L,
      F,
      Y,
      G,
      U
    ) : k(
      b,
      $,
      L,
      F,
      Y,
      G,
      U
    );
  }, R = (b, $, M, V, L, F, Y, G) => {
    let U, N;
    const { props: te, shapeFlag: q, transition: Q, dirs: se } = b;
    if (U = b.el = a(
      b.type,
      F,
      te && te.is,
      te
    ), q & 8 ? c(U, b.children) : q & 16 && H(
      b.children,
      U,
      null,
      V,
      L,
      Ur(b, F),
      Y,
      G
    ), se && _n(b, null, V, "created"), I(U, b, b.scopeId, Y, V), te) {
      for (const Te in te)
        Te !== "value" && !uo(Te) && s(U, Te, null, te[Te], F, V);
      "value" in te && s(U, "value", null, te.value, F), (N = te.onVnodeBeforeMount) && Mt(N, V, b);
    }
    se && _n(b, null, V, "beforeMount");
    const pe = Ud(L, Q);
    pe && Q.beforeEnter(U), o(U, $, M), ((N = te && te.onVnodeMounted) || pe || se) && at(() => {
      N && Mt(N, V, b), pe && Q.enter(U), se && _n(b, null, V, "mounted");
    }, L);
  }, I = (b, $, M, V, L) => {
    if (M && h(b, M), V)
      for (let F = 0; F < V.length; F++)
        h(b, V[F]);
    if (L) {
      let F = L.subTree;
      if ($ === F || Kl(F.type) && (F.ssContent === $ || F.ssFallback === $)) {
        const Y = L.vnode;
        I(
          b,
          Y,
          Y.scopeId,
          Y.slotScopeIds,
          L.parent
        );
      }
    }
  }, H = (b, $, M, V, L, F, Y, G, U = 0) => {
    for (let N = U; N < b.length; N++) {
      const te = b[N] = G ? pn(b[N]) : Rt(b[N]);
      y(
        null,
        te,
        $,
        M,
        V,
        L,
        F,
        Y,
        G
      );
    }
  }, k = (b, $, M, V, L, F, Y) => {
    const G = $.el = b.el;
    let { patchFlag: U, dynamicChildren: N, dirs: te } = $;
    U |= b.patchFlag & 16;
    const q = b.props || Ae, Q = $.props || Ae;
    let se;
    if (M && xn(M, !1), (se = Q.onVnodeBeforeUpdate) && Mt(se, M, $, b), te && _n($, b, M, "beforeUpdate"), M && xn(M, !0), (q.innerHTML && Q.innerHTML == null || q.textContent && Q.textContent == null) && c(G, ""), N ? j(
      b.dynamicChildren,
      N,
      G,
      M,
      V,
      Ur($, L),
      F
    ) : Y || ne(
      b,
      $,
      G,
      null,
      M,
      V,
      Ur($, L),
      F,
      !1
    ), U > 0) {
      if (U & 16)
        K(G, q, Q, M, L);
      else if (U & 2 && q.class !== Q.class && s(G, "class", null, Q.class, L), U & 4 && s(G, "style", q.style, Q.style, L), U & 8) {
        const pe = $.dynamicProps;
        for (let Te = 0; Te < pe.length; Te++) {
          const ye = pe[Te], Ze = q[ye], Ge = Q[ye];
          (Ge !== Ze || ye === "value") && s(G, ye, Ze, Ge, L, M);
        }
      }
      U & 1 && b.children !== $.children && c(G, $.children);
    } else !Y && N == null && K(G, q, Q, M, L);
    ((se = Q.onVnodeUpdated) || te) && at(() => {
      se && Mt(se, M, $, b), te && _n($, b, M, "updated");
    }, V);
  }, j = (b, $, M, V, L, F, Y) => {
    for (let G = 0; G < $.length; G++) {
      const U = b[G], N = $[G], te = (
        // oldVNode may be an errored async setup() component inside Suspense
        // which will not have a mounted element
        U.el && // - In the case of a Fragment, we need to provide the actual parent
        // of the Fragment itself so it can move its children.
        (U.type === ke || // - In the case of different nodes, there is going to be a replacement
        // which also requires the correct parent container
        !so(U, N) || // - In the case of a component, it could contain anything.
        U.shapeFlag & 70) ? f(U.el) : (
          // In other cases, the parent container is not actually used so we
          // just pass the block element here to avoid a DOM parentNode call.
          M
        )
      );
      y(
        U,
        N,
        te,
        null,
        V,
        L,
        F,
        Y,
        !0
      );
    }
  }, K = (b, $, M, V, L) => {
    if ($ !== M) {
      if ($ !== Ae)
        for (const F in $)
          !uo(F) && !(F in M) && s(
            b,
            F,
            $[F],
            null,
            L,
            V
          );
      for (const F in M) {
        if (uo(F)) continue;
        const Y = M[F], G = $[F];
        Y !== G && F !== "value" && s(b, F, G, Y, L, V);
      }
      "value" in M && s(b, "value", $.value, M.value, L);
    }
  }, J = (b, $, M, V, L, F, Y, G, U) => {
    const N = $.el = b ? b.el : i(""), te = $.anchor = b ? b.anchor : i("");
    let { patchFlag: q, dynamicChildren: Q, slotScopeIds: se } = $;
    se && (G = G ? G.concat(se) : se), b == null ? (o(N, M, V), o(te, M, V), H(
      // #10007
      // such fragment like `<></>` will be compiled into
      // a fragment which doesn't have a children.
      // In this case fallback to an empty array
      $.children || [],
      M,
      te,
      L,
      F,
      Y,
      G,
      U
    )) : q > 0 && q & 64 && Q && // #2715 the previous fragment could've been a BAILed one as a result
    // of renderSlot() with no valid children
    b.dynamicChildren ? (j(
      b.dynamicChildren,
      Q,
      M,
      L,
      F,
      Y,
      G
    ), // #2080 if the stable fragment has a key, it's a <template v-for> that may
    //  get moved around. Make sure all root level vnodes inherit el.
    // #2134 or if it's a component root, it may also get moved around
    // as the component is being moved.
    ($.key != null || L && $ === L.subTree) && Gs(
      b,
      $,
      !0
      /* shallow */
    )) : ne(
      b,
      $,
      M,
      te,
      L,
      F,
      Y,
      G,
      U
    );
  }, ge = (b, $, M, V, L, F, Y, G, U) => {
    $.slotScopeIds = G, b == null ? $.shapeFlag & 512 ? L.ctx.activate(
      $,
      M,
      V,
      Y,
      U
    ) : ce(
      $,
      M,
      V,
      L,
      F,
      Y,
      U
    ) : Pe(b, $, U);
  }, ce = (b, $, M, V, L, F, Y) => {
    const G = b.component = uf(
      b,
      V,
      L
    );
    if (Cl(b) && (G.ctx.renderer = De), cf(G, !1, Y), G.asyncDep) {
      if (L && L.registerDep(G, ue, Y), !b.el) {
        const U = G.subTree = W(Vt);
        v(null, U, $, M);
      }
    } else
      ue(
        G,
        b,
        $,
        M,
        L,
        F,
        Y
      );
  }, Pe = (b, $, M) => {
    const V = $.component = b.component;
    if (tf(b, $, M))
      if (V.asyncDep && !V.asyncResolved) {
        ee(V, $, M);
        return;
      } else
        V.next = $, V.update();
    else
      $.el = b.el, V.vnode = $;
  }, ue = (b, $, M, V, L, F, Y) => {
    const G = () => {
      if (b.isMounted) {
        let { next: q, bu: Q, u: se, parent: pe, vnode: Te } = b;
        {
          const ct = zl(b);
          if (ct) {
            q && (q.el = Te.el, ee(b, q, Y)), ct.asyncDep.then(() => {
              b.isUnmounted || G();
            });
            return;
          }
        }
        let ye = q, Ze;
        xn(b, !1), q ? (q.el = Te.el, ee(b, q, Y)) : q = Te, Q && Uo(Q), (Ze = q.props && q.props.onVnodeBeforeUpdate) && Mt(Ze, pe, q, Te), xn(b, !0);
        const Ge = za(b), ut = b.subTree;
        b.subTree = Ge, y(
          ut,
          Ge,
          // parent may have changed if it's in a teleport
          f(ut.el),
          // anchor may have changed if it's in a fragment
          _e(ut),
          b,
          L,
          F
        ), q.el = Ge.el, ye === null && nf(b, Ge.el), se && at(se, L), (Ze = q.props && q.props.onVnodeUpdated) && at(
          () => Mt(Ze, pe, q, Te),
          L
        );
      } else {
        let q;
        const { el: Q, props: se } = $, { bm: pe, m: Te, parent: ye, root: Ze, type: Ge } = b, ut = Kn($);
        xn(b, !1), pe && Uo(pe), !ut && (q = se && se.onVnodeBeforeMount) && Mt(q, ye, $), xn(b, !0);
        {
          Ze.ce && Ze.ce._injectChildStyle(Ge);
          const ct = b.subTree = za(b);
          y(
            null,
            ct,
            M,
            V,
            b,
            L,
            F
          ), $.el = ct.el;
        }
        if (Te && at(Te, L), !ut && (q = se && se.onVnodeMounted)) {
          const ct = $;
          at(
            () => Mt(q, ye, ct),
            L
          );
        }
        ($.shapeFlag & 256 || ye && Kn(ye.vnode) && ye.vnode.shapeFlag & 256) && b.a && at(b.a, L), b.isMounted = !0, $ = M = V = null;
      }
    };
    b.scope.on();
    const U = b.effect = new Gi(G);
    b.scope.off();
    const N = b.update = U.run.bind(U), te = b.job = U.runIfDirty.bind(U);
    te.i = b, te.id = b.uid, U.scheduler = () => Ws(te), xn(b, !0), N();
  }, ee = (b, $, M) => {
    $.component = b;
    const V = b.vnode.props;
    b.vnode = $, b.next = null, Vd(b, $.props, V, M), Hd(b, $.children, M), bn(), Ba(b), wn();
  }, ne = (b, $, M, V, L, F, Y, G, U = !1) => {
    const N = b && b.children, te = b ? b.shapeFlag : 0, q = $.children, { patchFlag: Q, shapeFlag: se } = $;
    if (Q > 0) {
      if (Q & 128) {
        je(
          N,
          q,
          M,
          V,
          L,
          F,
          Y,
          G,
          U
        );
        return;
      } else if (Q & 256) {
        he(
          N,
          q,
          M,
          V,
          L,
          F,
          Y,
          G,
          U
        );
        return;
      }
    }
    se & 8 ? (te & 16 && ve(N, L, F), q !== N && c(M, q)) : te & 16 ? se & 16 ? je(
      N,
      q,
      M,
      V,
      L,
      F,
      Y,
      G,
      U
    ) : ve(N, L, F, !0) : (te & 8 && c(M, ""), se & 16 && H(
      q,
      M,
      V,
      L,
      F,
      Y,
      G,
      U
    ));
  }, he = (b, $, M, V, L, F, Y, G, U) => {
    b = b || zn, $ = $ || zn;
    const N = b.length, te = $.length, q = Math.min(N, te);
    let Q;
    for (Q = 0; Q < q; Q++) {
      const se = $[Q] = U ? pn($[Q]) : Rt($[Q]);
      y(
        b[Q],
        se,
        M,
        null,
        L,
        F,
        Y,
        G,
        U
      );
    }
    N > te ? ve(
      b,
      L,
      F,
      !0,
      !1,
      q
    ) : H(
      $,
      M,
      V,
      L,
      F,
      Y,
      G,
      U,
      q
    );
  }, je = (b, $, M, V, L, F, Y, G, U) => {
    let N = 0;
    const te = $.length;
    let q = b.length - 1, Q = te - 1;
    for (; N <= q && N <= Q; ) {
      const se = b[N], pe = $[N] = U ? pn($[N]) : Rt($[N]);
      if (so(se, pe))
        y(
          se,
          pe,
          M,
          null,
          L,
          F,
          Y,
          G,
          U
        );
      else
        break;
      N++;
    }
    for (; N <= q && N <= Q; ) {
      const se = b[q], pe = $[Q] = U ? pn($[Q]) : Rt($[Q]);
      if (so(se, pe))
        y(
          se,
          pe,
          M,
          null,
          L,
          F,
          Y,
          G,
          U
        );
      else
        break;
      q--, Q--;
    }
    if (N > q) {
      if (N <= Q) {
        const se = Q + 1, pe = se < te ? $[se].el : V;
        for (; N <= Q; )
          y(
            null,
            $[N] = U ? pn($[N]) : Rt($[N]),
            M,
            pe,
            L,
            F,
            Y,
            G,
            U
          ), N++;
      }
    } else if (N > Q)
      for (; N <= q; )
        Je(b[N], L, F, !0), N++;
    else {
      const se = N, pe = N, Te = /* @__PURE__ */ new Map();
      for (N = pe; N <= Q; N++) {
        const We = $[N] = U ? pn($[N]) : Rt($[N]);
        We.key != null && Te.set(We.key, N);
      }
      let ye, Ze = 0;
      const Ge = Q - pe + 1;
      let ut = !1, ct = 0;
      const an = new Array(Ge);
      for (N = 0; N < Ge; N++) an[N] = 0;
      for (N = se; N <= q; N++) {
        const We = b[N];
        if (Ze >= Ge) {
          Je(We, L, F, !0);
          continue;
        }
        let Qe;
        if (We.key != null)
          Qe = Te.get(We.key);
        else
          for (ye = pe; ye <= Q; ye++)
            if (an[ye - pe] === 0 && so(We, $[ye])) {
              Qe = ye;
              break;
            }
        Qe === void 0 ? Je(We, L, F, !0) : (an[Qe - pe] = N + 1, Qe >= ct ? ct = Qe : ut = !0, y(
          We,
          $[Qe],
          M,
          null,
          L,
          F,
          Y,
          G,
          U
        ), Ze++);
      }
      const ln = ut ? Gd(an) : zn;
      for (ye = ln.length - 1, N = Ge - 1; N >= 0; N--) {
        const We = pe + N, Qe = $[We], Sa = We + 1 < te ? $[We + 1].el : V;
        an[N] === 0 ? y(
          null,
          Qe,
          M,
          Sa,
          L,
          F,
          Y,
          G,
          U
        ) : ut && (ye < 0 || N !== ln[ye] ? ze(Qe, M, Sa, 2) : ye--);
      }
    }
  }, ze = (b, $, M, V, L = null) => {
    const { el: F, type: Y, transition: G, children: U, shapeFlag: N } = b;
    if (N & 6) {
      ze(b.component.subTree, $, M, V);
      return;
    }
    if (N & 128) {
      b.suspense.move($, M, V);
      return;
    }
    if (N & 64) {
      Y.move(b, $, M, De);
      return;
    }
    if (Y === ke) {
      o(F, $, M);
      for (let q = 0; q < U.length; q++)
        ze(U[q], $, M, V);
      o(b.anchor, $, M);
      return;
    }
    if (Y === Yo) {
      m(b, $, M);
      return;
    }
    if (V !== 2 && N & 1 && G)
      if (V === 0)
        G.beforeEnter(F), o(F, $, M), at(() => G.enter(F), L);
      else {
        const { leave: q, delayLeave: Q, afterLeave: se } = G, pe = () => o(F, $, M), Te = () => {
          q(F, () => {
            pe(), se && se();
          });
        };
        Q ? Q(F, pe, Te) : Te();
      }
    else
      o(F, $, M);
  }, Je = (b, $, M, V = !1, L = !1) => {
    const {
      type: F,
      props: Y,
      ref: G,
      children: U,
      dynamicChildren: N,
      shapeFlag: te,
      patchFlag: q,
      dirs: Q,
      cacheIndex: se
    } = b;
    if (q === -2 && (L = !1), G != null && nr(G, null, M, b, !0), se != null && ($.renderCache[se] = void 0), te & 256) {
      $.ctx.deactivate(b);
      return;
    }
    const pe = te & 1 && Q, Te = !Kn(b);
    let ye;
    if (Te && (ye = Y && Y.onVnodeBeforeUnmount) && Mt(ye, $, b), te & 6)
      D(b.component, M, V);
    else {
      if (te & 128) {
        b.suspense.unmount(M, V);
        return;
      }
      pe && _n(b, null, $, "beforeUnmount"), te & 64 ? b.type.remove(
        b,
        $,
        M,
        De,
        V
      ) : N && // #5154
      // when v-once is used inside a block, setBlockTracking(-1) marks the
      // parent block with hasOnce: true
      // so that it doesn't take the fast path during unmount - otherwise
      // components nested in v-once are never unmounted.
      !N.hasOnce && // #1153: fast path should not be taken for non-stable (v-for) fragments
      (F !== ke || q > 0 && q & 64) ? ve(
        N,
        $,
        M,
        !1,
        !0
      ) : (F === ke && q & 384 || !L && te & 16) && ve(U, $, M), V && Mn(b);
    }
    (Te && (ye = Y && Y.onVnodeUnmounted) || pe) && at(() => {
      ye && Mt(ye, $, b), pe && _n(b, null, $, "unmounted");
    }, M);
  }, Mn = (b) => {
    const { type: $, el: M, anchor: V, transition: L } = b;
    if ($ === ke) {
      sn(M, V);
      return;
    }
    if ($ === Yo) {
      x(b);
      return;
    }
    const F = () => {
      r(M), L && !L.persisted && L.afterLeave && L.afterLeave();
    };
    if (b.shapeFlag & 1 && L && !L.persisted) {
      const { leave: Y, delayLeave: G } = L, U = () => Y(M, F);
      G ? G(b.el, F, U) : U();
    } else
      F();
  }, sn = (b, $) => {
    let M;
    for (; b !== $; )
      M = p(b), r(b), b = M;
    r($);
  }, D = (b, $, M) => {
    const { bum: V, scope: L, job: F, subTree: Y, um: G, m: U, a: N } = b;
    Na(U), Na(N), V && Uo(V), L.stop(), F && (F.flags |= 8, Je(Y, b, $, M)), G && at(G, $), at(() => {
      b.isUnmounted = !0;
    }, $), $ && $.pendingBranch && !$.isUnmounted && b.asyncDep && !b.asyncResolved && b.suspenseId === $.pendingId && ($.deps--, $.deps === 0 && $.resolve());
  }, ve = (b, $, M, V = !1, L = !1, F = 0) => {
    for (let Y = F; Y < b.length; Y++)
      Je(b[Y], $, M, V, L);
  }, _e = (b) => {
    if (b.shapeFlag & 6)
      return _e(b.component.subTree);
    if (b.shapeFlag & 128)
      return b.suspense.next();
    const $ = p(b.anchor || b.el), M = $ && $[yl];
    return M ? p(M) : $;
  };
  let Ee = !1;
  const $e = (b, $, M) => {
    b == null ? $._vnode && Je($._vnode, null, null, !0) : y(
      $._vnode || null,
      b,
      $,
      null,
      null,
      null,
      M
    ), $._vnode = b, Ee || (Ee = !0, Ba(), ml(), Ee = !1);
  }, De = {
    p: y,
    um: Je,
    m: ze,
    r: Mn,
    mt: ce,
    mc: H,
    pc: ne,
    pbc: j,
    n: _e,
    o: e
  };
  return {
    render: $e,
    hydrate: void 0,
    createApp: Ld($e)
  };
}
function Ur({ type: e, props: t }, n) {
  return n === "svg" && e === "foreignObject" || n === "mathml" && e === "annotation-xml" && t && t.encoding && t.encoding.includes("html") ? void 0 : n;
}
function xn({ effect: e, job: t }, n) {
  n ? (e.flags |= 32, t.flags |= 4) : (e.flags &= -33, t.flags &= -5);
}
function Ud(e, t) {
  return (!e || e && !e.pendingBranch) && t && !t.persisted;
}
function Gs(e, t, n = !1) {
  const o = e.children, r = t.children;
  if (re(o) && re(r))
    for (let s = 0; s < o.length; s++) {
      const a = o[s];
      let i = r[s];
      i.shapeFlag & 1 && !i.dynamicChildren && ((i.patchFlag <= 0 || i.patchFlag === 32) && (i = r[s] = pn(r[s]), i.el = a.el), !n && i.patchFlag !== -2 && Gs(a, i)), i.type === $r && (i.el = a.el);
    }
}
function Gd(e) {
  const t = e.slice(), n = [0];
  let o, r, s, a, i;
  const l = e.length;
  for (o = 0; o < l; o++) {
    const u = e[o];
    if (u !== 0) {
      if (r = n[n.length - 1], e[r] < u) {
        t[o] = r, n.push(o);
        continue;
      }
      for (s = 0, a = n.length - 1; s < a; )
        i = s + a >> 1, e[n[i]] < u ? s = i + 1 : a = i;
      u < e[n[s]] && (s > 0 && (t[o] = n[s - 1]), n[s] = o);
    }
  }
  for (s = n.length, a = n[s - 1]; s-- > 0; )
    n[s] = a, a = t[a];
  return n;
}
function zl(e) {
  const t = e.subTree.component;
  if (t)
    return t.asyncDep && !t.asyncResolved ? t : zl(t);
}
function Na(e) {
  if (e)
    for (let t = 0; t < e.length; t++)
      e[t].flags |= 8;
}
const Yd = Symbol.for("v-scx"), qd = () => Zt(Yd);
function Me(e, t) {
  return Cr(e, null, t);
}
function jl(e, t) {
  return Cr(
    e,
    null,
    { flush: "post" }
  );
}
function be(e, t, n) {
  return Cr(e, t, n);
}
function Cr(e, t, n = Ae) {
  const { immediate: o, deep: r, flush: s, once: a } = n, i = Ke({}, n), l = t && o || !t && s !== "post";
  let u;
  if (Co) {
    if (s === "sync") {
      const h = qd();
      u = h.__watcherHandles || (h.__watcherHandles = []);
    } else if (!l) {
      const h = () => {
      };
      return h.stop = It, h.resume = It, h.pause = It, h;
    }
  }
  const c = Ye;
  i.call = (h, g, y) => Ft(h, c, g, y);
  let f = !1;
  s === "post" ? i.scheduler = (h) => {
    at(h, c && c.suspense);
  } : s !== "sync" && (f = !0, i.scheduler = (h, g) => {
    g ? h() : Ws(h);
  }), i.augmentJob = (h) => {
    t && (h.flags |= 4), f && (h.flags |= 2, c && (h.id = c.uid, h.i = c));
  };
  const p = ld(e, t, i);
  return Co && (u ? u.push(p) : l && p()), p;
}
function Xd(e, t, n) {
  const o = this.proxy, r = Fe(e) ? e.includes(".") ? Hl(o, e) : () => o[e] : e.bind(o, o);
  let s;
  ae(t) ? s = t : (s = t.handler, n = t);
  const a = Oo(this), i = Cr(r, s.bind(o), n);
  return a(), i;
}
function Hl(e, t) {
  const n = t.split(".");
  return () => {
    let o = e;
    for (let r = 0; r < n.length && o; r++)
      o = o[n[r]];
    return o;
  };
}
const Jd = (e, t) => t === "modelValue" || t === "model-value" ? e.modelModifiers : e[`${t}Modifiers`] || e[`${qe(t)}Modifiers`] || e[`${pt(t)}Modifiers`];
function Zd(e, t, ...n) {
  if (e.isUnmounted) return;
  const o = e.vnode.props || Ae;
  let r = n;
  const s = t.startsWith("update:"), a = s && Jd(o, t.slice(7));
  a && (a.trim && (r = n.map((c) => Fe(c) ? c.trim() : c)), a.number && (r = n.map(ss)));
  let i, l = o[i = co(t)] || // also try camelCase event handler (#2249)
  o[i = co(qe(t))];
  !l && s && (l = o[i = co(pt(t))]), l && Ft(
    l,
    e,
    6,
    r
  );
  const u = o[i + "Once"];
  if (u) {
    if (!e.emitted)
      e.emitted = {};
    else if (e.emitted[i])
      return;
    e.emitted[i] = !0, Ft(
      u,
      e,
      6,
      r
    );
  }
}
function Wl(e, t, n = !1) {
  const o = t.emitsCache, r = o.get(e);
  if (r !== void 0)
    return r;
  const s = e.emits;
  let a = {}, i = !1;
  if (!ae(e)) {
    const l = (u) => {
      const c = Wl(u, t, !0);
      c && (i = !0, Ke(a, c));
    };
    !n && t.mixins.length && t.mixins.forEach(l), e.extends && l(e.extends), e.mixins && e.mixins.forEach(l);
  }
  return !s && !i ? (Ie(e) && o.set(e, null), null) : (re(s) ? s.forEach((l) => a[l] = null) : Ke(a, s), Ie(e) && o.set(e, a), a);
}
function Sr(e, t) {
  return !e || !ur(t) ? !1 : (t = t.slice(2).replace(/Once$/, ""), Ce(e, t[0].toLowerCase() + t.slice(1)) || Ce(e, pt(t)) || Ce(e, t));
}
function za(e) {
  const {
    type: t,
    vnode: n,
    proxy: o,
    withProxy: r,
    propsOptions: [s],
    slots: a,
    attrs: i,
    emit: l,
    render: u,
    renderCache: c,
    props: f,
    data: p,
    setupState: h,
    ctx: g,
    inheritAttrs: y
  } = e, C = tr(e);
  let v, w;
  try {
    if (n.shapeFlag & 4) {
      const x = r || o, A = x;
      v = Rt(
        u.call(
          A,
          x,
          c,
          f,
          h,
          p,
          g
        )
      ), w = i;
    } else {
      const x = t;
      v = Rt(
        x.length > 1 ? x(
          f,
          { attrs: i, slots: a, emit: l }
        ) : x(
          f,
          null
        )
      ), w = t.props ? i : Qd(i);
    }
  } catch (x) {
    go.length = 0, _r(x, e, 1), v = W(Vt);
  }
  let m = v;
  if (w && y !== !1) {
    const x = Object.keys(w), { shapeFlag: A } = m;
    x.length && A & 7 && (s && x.some(Is) && (w = ef(
      w,
      s
    )), m = mn(m, w, !1, !0));
  }
  return n.dirs && (m = mn(m, null, !1, !0), m.dirs = m.dirs ? m.dirs.concat(n.dirs) : n.dirs), n.transition && Ks(m, n.transition), v = m, tr(C), v;
}
const Qd = (e) => {
  let t;
  for (const n in e)
    (n === "class" || n === "style" || ur(n)) && ((t || (t = {}))[n] = e[n]);
  return t;
}, ef = (e, t) => {
  const n = {};
  for (const o in e)
    (!Is(o) || !(o.slice(9) in t)) && (n[o] = e[o]);
  return n;
};
function tf(e, t, n) {
  const { props: o, children: r, component: s } = e, { props: a, children: i, patchFlag: l } = t, u = s.emitsOptions;
  if (t.dirs || t.transition)
    return !0;
  if (n && l >= 0) {
    if (l & 1024)
      return !0;
    if (l & 16)
      return o ? ja(o, a, u) : !!a;
    if (l & 8) {
      const c = t.dynamicProps;
      for (let f = 0; f < c.length; f++) {
        const p = c[f];
        if (a[p] !== o[p] && !Sr(u, p))
          return !0;
      }
    }
  } else
    return (r || i) && (!i || !i.$stable) ? !0 : o === a ? !1 : o ? a ? ja(o, a, u) : !0 : !!a;
  return !1;
}
function ja(e, t, n) {
  const o = Object.keys(t);
  if (o.length !== Object.keys(e).length)
    return !0;
  for (let r = 0; r < o.length; r++) {
    const s = o[r];
    if (t[s] !== e[s] && !Sr(n, s))
      return !0;
  }
  return !1;
}
function nf({ vnode: e, parent: t }, n) {
  for (; t; ) {
    const o = t.subTree;
    if (o.suspense && o.suspense.activeBranch === e && (o.el = e.el), o === e)
      (e = t.vnode).el = n, t = t.parent;
    else
      break;
  }
}
const Kl = (e) => e.__isSuspense;
function of(e, t) {
  t && t.pendingBranch ? re(e) ? t.effects.push(...e) : t.effects.push(e) : dd(e);
}
const ke = Symbol.for("v-fgt"), $r = Symbol.for("v-txt"), Vt = Symbol.for("v-cmt"), Yo = Symbol.for("v-stc"), go = [];
let ht = null;
function _(e = !1) {
  go.push(ht = e ? null : []);
}
function rf() {
  go.pop(), ht = go[go.length - 1] || null;
}
let _o = 1;
function Ha(e, t = !1) {
  _o += e, e < 0 && ht && t && (ht.hasOnce = !0);
}
function Ul(e) {
  return e.dynamicChildren = _o > 0 ? ht || zn : null, rf(), _o > 0 && ht && ht.push(e), e;
}
function oe(e, t, n, o, r, s) {
  return Ul(
    Z(
      e,
      t,
      n,
      o,
      r,
      s,
      !0
    )
  );
}
function E(e, t, n, o, r) {
  return Ul(
    W(
      e,
      t,
      n,
      o,
      r,
      !0
    )
  );
}
function xo(e) {
  return e ? e.__v_isVNode === !0 : !1;
}
function so(e, t) {
  return e.type === t.type && e.key === t.key;
}
const Gl = ({ key: e }) => e ?? null, qo = ({
  ref: e,
  ref_key: t,
  ref_for: n
}) => (typeof e == "number" && (e = "" + e), e != null ? Fe(e) || He(e) || ae(e) ? { i: Ue, r: e, k: t, f: !!n } : e : null);
function Z(e, t = null, n = null, o = 0, r = null, s = e === ke ? 0 : 1, a = !1, i = !1) {
  const l = {
    __v_isVNode: !0,
    __v_skip: !0,
    type: e,
    props: t,
    key: t && Gl(t),
    ref: t && qo(t),
    scopeId: vl,
    slotScopeIds: null,
    children: n,
    component: null,
    suspense: null,
    ssContent: null,
    ssFallback: null,
    dirs: null,
    transition: null,
    el: null,
    anchor: null,
    target: null,
    targetStart: null,
    targetAnchor: null,
    staticCount: 0,
    shapeFlag: s,
    patchFlag: o,
    dynamicProps: r,
    dynamicChildren: null,
    appContext: null,
    ctx: Ue
  };
  return i ? (Ys(l, n), s & 128 && e.normalize(l)) : n && (l.shapeFlag |= Fe(n) ? 8 : 16), _o > 0 && // avoid a block node from tracking itself
  !a && // has current parent block
  ht && // presence of a patch flag indicates this node needs patching on updates.
  // component nodes also should always be patched, because even if the
  // component doesn't need to update, it needs to persist the instance on to
  // the next vnode so that it can be properly unmounted later.
  (l.patchFlag > 0 || s & 6) && // the EVENTS flag is only for hydration and if it is the only flag, the
  // vnode should not be considered dynamic due to handler caching.
  l.patchFlag !== 32 && ht.push(l), l;
}
const W = sf;
function sf(e, t = null, n = null, o = 0, r = null, s = !1) {
  if ((!e || e === $l) && (e = Vt), xo(e)) {
    const i = mn(
      e,
      t,
      !0
      /* mergeRef: true */
    );
    return n && Ys(i, n), _o > 0 && !s && ht && (i.shapeFlag & 6 ? ht[ht.indexOf(e)] = i : ht.push(i)), i.patchFlag = -2, i;
  }
  if (hf(e) && (e = e.__vccOpts), t) {
    t = we(t);
    let { class: i, style: l } = t;
    i && !Fe(i) && (t.class = le(i)), Ie(l) && (Hs(l) && !re(l) && (l = Ke({}, l)), t.style = nt(l));
  }
  const a = Fe(e) ? 1 : Kl(e) ? 128 : pd(e) ? 64 : Ie(e) ? 4 : ae(e) ? 2 : 0;
  return Z(
    e,
    t,
    n,
    o,
    r,
    a,
    s,
    !0
  );
}
function we(e) {
  return e ? Hs(e) || Dl(e) ? Ke({}, e) : e : null;
}
function mn(e, t, n = !1, o = !1) {
  const { props: r, ref: s, patchFlag: a, children: i, transition: l } = e, u = t ? z(r || {}, t) : r, c = {
    __v_isVNode: !0,
    __v_skip: !0,
    type: e.type,
    props: u,
    key: u && Gl(u),
    ref: t && t.ref ? (
      // #2078 in the case of <component :is="vnode" ref="extra"/>
      // if the vnode itself already has a ref, cloneVNode will need to merge
      // the refs so the single vnode can be set on multiple refs
      n && s ? re(s) ? s.concat(qo(t)) : [s, qo(t)] : qo(t)
    ) : s,
    scopeId: e.scopeId,
    slotScopeIds: e.slotScopeIds,
    children: i,
    target: e.target,
    targetStart: e.targetStart,
    targetAnchor: e.targetAnchor,
    staticCount: e.staticCount,
    shapeFlag: e.shapeFlag,
    // if the vnode is cloned with extra props, we can no longer assume its
    // existing patch flag to be reliable and need to add the FULL_PROPS flag.
    // note: preserve flag for fragments since they use the flag for children
    // fast paths only.
    patchFlag: t && e.type !== ke ? a === -1 ? 16 : a | 16 : a,
    dynamicProps: e.dynamicProps,
    dynamicChildren: e.dynamicChildren,
    appContext: e.appContext,
    dirs: e.dirs,
    transition: l,
    // These should technically only be non-null on mounted VNodes. However,
    // they *should* be copied for kept-alive vnodes. So we just always copy
    // them since them being non-null during a mount doesn't affect the logic as
    // they will simply be overwritten.
    component: e.component,
    suspense: e.suspense,
    ssContent: e.ssContent && mn(e.ssContent),
    ssFallback: e.ssFallback && mn(e.ssFallback),
    el: e.el,
    anchor: e.anchor,
    ctx: e.ctx,
    ce: e.ce
  };
  return l && o && Ks(
    c,
    l.clone(c)
  ), c;
}
function At(e = " ", t = 0) {
  return W($r, null, e, t);
}
function Wa(e, t) {
  const n = W(Yo, null, e);
  return n.staticCount = t, n;
}
function Be(e = "", t = !1) {
  return t ? (_(), E(Vt, null, e)) : W(Vt, null, e);
}
function Rt(e) {
  return e == null || typeof e == "boolean" ? W(Vt) : re(e) ? W(
    ke,
    null,
    // #3666, avoid reference pollution when reusing vnode
    e.slice()
  ) : xo(e) ? pn(e) : W($r, null, String(e));
}
function pn(e) {
  return e.el === null && e.patchFlag !== -1 || e.memo ? e : mn(e);
}
function Ys(e, t) {
  let n = 0;
  const { shapeFlag: o } = e;
  if (t == null)
    t = null;
  else if (re(t))
    n = 16;
  else if (typeof t == "object")
    if (o & 65) {
      const r = t.default;
      r && (r._c && (r._d = !1), Ys(e, r()), r._c && (r._d = !0));
      return;
    } else {
      n = 32;
      const r = t._;
      !r && !Dl(t) ? t._ctx = Ue : r === 3 && Ue && (Ue.slots._ === 1 ? t._ = 1 : (t._ = 2, e.patchFlag |= 1024));
    }
  else ae(t) ? (t = { default: t, _ctx: Ue }, n = 32) : (t = String(t), o & 64 ? (n = 16, t = [At(t)]) : n = 8);
  e.children = t, e.shapeFlag |= n;
}
function z(...e) {
  const t = {};
  for (let n = 0; n < e.length; n++) {
    const o = e[n];
    for (const r in o)
      if (r === "class")
        t.class !== o.class && (t.class = le([t.class, o.class]));
      else if (r === "style")
        t.style = nt([t.style, o.style]);
      else if (ur(r)) {
        const s = t[r], a = o[r];
        a && s !== a && !(re(s) && s.includes(a)) && (t[r] = s ? [].concat(s, a) : a);
      } else r !== "" && (t[r] = o[r]);
  }
  return t;
}
function Mt(e, t, n, o = null) {
  Ft(e, t, 7, [
    n,
    o
  ]);
}
const af = Bl();
let lf = 0;
function uf(e, t, n) {
  const o = e.type, r = (t ? t.appContext : e.appContext) || af, s = {
    uid: lf++,
    vnode: e,
    type: o,
    parent: t,
    appContext: r,
    root: null,
    // to be immediately set
    next: null,
    subTree: null,
    // will be set synchronously right after creation
    effect: null,
    update: null,
    // will be set synchronously right after creation
    job: null,
    scope: new Wi(
      !0
      /* detached */
    ),
    render: null,
    proxy: null,
    exposed: null,
    exposeProxy: null,
    withProxy: null,
    provides: t ? t.provides : Object.create(r.provides),
    ids: t ? t.ids : ["", 0, 0],
    accessCache: null,
    renderCache: [],
    // local resolved assets
    components: null,
    directives: null,
    // resolved props and emits options
    propsOptions: Il(o, r),
    emitsOptions: Wl(o, r),
    // emit
    emit: null,
    // to be set immediately
    emitted: null,
    // props default value
    propsDefaults: Ae,
    // inheritAttrs
    inheritAttrs: o.inheritAttrs,
    // state
    ctx: Ae,
    data: Ae,
    props: Ae,
    attrs: Ae,
    slots: Ae,
    refs: Ae,
    setupState: Ae,
    setupContext: null,
    // suspense related
    suspense: n,
    suspenseId: n ? n.pendingId : 0,
    asyncDep: null,
    asyncResolved: !1,
    // lifecycle hooks
    // not using enums here because it results in computed properties
    isMounted: !1,
    isUnmounted: !1,
    isDeactivated: !1,
    bc: null,
    c: null,
    bm: null,
    m: null,
    bu: null,
    u: null,
    um: null,
    bum: null,
    da: null,
    a: null,
    rtg: null,
    rtc: null,
    ec: null,
    sp: null
  };
  return s.ctx = { _: s }, s.root = t ? t.root : s, s.emit = Zd.bind(null, s), e.ce && e.ce(s), s;
}
let Ye = null;
const jt = () => Ye || Ue;
let rr, vs;
{
  const e = hr(), t = (n, o) => {
    let r;
    return (r = e[n]) || (r = e[n] = []), r.push(o), (s) => {
      r.length > 1 ? r.forEach((a) => a(s)) : r[0](s);
    };
  };
  rr = t(
    "__VUE_INSTANCE_SETTERS__",
    (n) => Ye = n
  ), vs = t(
    "__VUE_SSR_SETTERS__",
    (n) => Co = n
  );
}
const Oo = (e) => {
  const t = Ye;
  return rr(e), e.scope.on(), () => {
    e.scope.off(), rr(t);
  };
}, Ka = () => {
  Ye && Ye.scope.off(), rr(null);
};
function Yl(e) {
  return e.vnode.shapeFlag & 4;
}
let Co = !1;
function cf(e, t = !1, n = !1) {
  t && vs(t);
  const { props: o, children: r } = e.vnode, s = Yl(e);
  Fd(e, o, s, t), jd(e, r, n);
  const a = s ? df(e, t) : void 0;
  return t && vs(!1), a;
}
function df(e, t) {
  const n = e.type;
  e.accessCache = /* @__PURE__ */ Object.create(null), e.proxy = new Proxy(e.ctx, Ed);
  const { setup: o } = n;
  if (o) {
    bn();
    const r = e.setupContext = o.length > 1 ? Xl(e) : null, s = Oo(e), a = Eo(
      o,
      e,
      0,
      [
        e.props,
        r
      ]
    ), i = Fi(a);
    if (wn(), s(), (i || e.sp) && !Kn(e) && xl(e), i) {
      if (a.then(Ka, Ka), t)
        return a.then((l) => {
          Ua(e, l);
        }).catch((l) => {
          _r(l, e, 0);
        });
      e.asyncDep = a;
    } else
      Ua(e, a);
  } else
    ql(e);
}
function Ua(e, t, n) {
  ae(t) ? e.type.__ssrInlineRender ? e.ssrRender = t : e.render = t : Ie(t) && (e.setupState = dl(t)), ql(e);
}
function ql(e, t, n) {
  const o = e.type;
  e.render || (e.render = o.render || It);
  {
    const r = Oo(e);
    bn();
    try {
      Bd(e);
    } finally {
      wn(), r();
    }
  }
}
const ff = {
  get(e, t) {
    return et(e, "get", ""), e[t];
  }
};
function Xl(e) {
  const t = (n) => {
    e.exposed = n || {};
  };
  return {
    attrs: new Proxy(e.attrs, ff),
    slots: e.slots,
    emit: e.emit,
    expose: t
  };
}
function Tr(e) {
  return e.exposed ? e.exposeProxy || (e.exposeProxy = new Proxy(dl(ul(e.exposed)), {
    get(t, n) {
      if (n in t)
        return t[n];
      if (n in mo)
        return mo[n](e);
    },
    has(t, n) {
      return n in t || n in mo;
    }
  })) : e.proxy;
}
function pf(e, t = !0) {
  return ae(e) ? e.displayName || e.name : e.name || t && e.__name;
}
function hf(e) {
  return ae(e) && "__vccOpts" in e;
}
const O = (e, t) => ad(e, t, Co);
function St(e, t, n) {
  const o = arguments.length;
  return o === 2 ? Ie(t) && !re(t) ? xo(t) ? W(e, null, [t]) : W(e, t) : W(e, null, t) : (o > 3 ? n = Array.prototype.slice.call(arguments, 2) : o === 3 && xo(n) && (n = [n]), W(e, t, n));
}
const mf = "3.5.13";
/**
* @vue/runtime-dom v3.5.13
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
let ys;
const Ga = typeof window < "u" && window.trustedTypes;
if (Ga)
  try {
    ys = /* @__PURE__ */ Ga.createPolicy("vue", {
      createHTML: (e) => e
    });
  } catch {
  }
const Jl = ys ? (e) => ys.createHTML(e) : (e) => e, gf = "http://www.w3.org/2000/svg", vf = "http://www.w3.org/1998/Math/MathML", qt = typeof document < "u" ? document : null, Ya = qt && /* @__PURE__ */ qt.createElement("template"), yf = {
  insert: (e, t, n) => {
    t.insertBefore(e, n || null);
  },
  remove: (e) => {
    const t = e.parentNode;
    t && t.removeChild(e);
  },
  createElement: (e, t, n, o) => {
    const r = t === "svg" ? qt.createElementNS(gf, e) : t === "mathml" ? qt.createElementNS(vf, e) : n ? qt.createElement(e, { is: n }) : qt.createElement(e);
    return e === "select" && o && o.multiple != null && r.setAttribute("multiple", o.multiple), r;
  },
  createText: (e) => qt.createTextNode(e),
  createComment: (e) => qt.createComment(e),
  setText: (e, t) => {
    e.nodeValue = t;
  },
  setElementText: (e, t) => {
    e.textContent = t;
  },
  parentNode: (e) => e.parentNode,
  nextSibling: (e) => e.nextSibling,
  querySelector: (e) => qt.querySelector(e),
  setScopeId(e, t) {
    e.setAttribute(t, "");
  },
  // __UNSAFE__
  // Reason: innerHTML.
  // Static content here can only come from compiled templates.
  // As long as the user only uses trusted templates, this is safe.
  insertStaticContent(e, t, n, o, r, s) {
    const a = n ? n.previousSibling : t.lastChild;
    if (r && (r === s || r.nextSibling))
      for (; t.insertBefore(r.cloneNode(!0), n), !(r === s || !(r = r.nextSibling)); )
        ;
    else {
      Ya.innerHTML = Jl(
        o === "svg" ? `<svg>${e}</svg>` : o === "mathml" ? `<math>${e}</math>` : e
      );
      const i = Ya.content;
      if (o === "svg" || o === "mathml") {
        const l = i.firstChild;
        for (; l.firstChild; )
          i.appendChild(l.firstChild);
        i.removeChild(l);
      }
      t.insertBefore(i, n);
    }
    return [
      // first
      a ? a.nextSibling : t.firstChild,
      // last
      n ? n.previousSibling : t.lastChild
    ];
  }
}, bf = Symbol("_vtc");
function wf(e, t, n) {
  const o = e[bf];
  o && (t = (t ? [t, ...o] : [...o]).join(" ")), t == null ? e.removeAttribute("class") : n ? e.setAttribute("class", t) : e.className = t;
}
const qa = Symbol("_vod"), _f = Symbol("_vsh"), xf = Symbol(""), Cf = /(^|;)\s*display\s*:/;
function Sf(e, t, n) {
  const o = e.style, r = Fe(n);
  let s = !1;
  if (n && !r) {
    if (t)
      if (Fe(t))
        for (const a of t.split(";")) {
          const i = a.slice(0, a.indexOf(":")).trim();
          n[i] == null && Xo(o, i, "");
        }
      else
        for (const a in t)
          n[a] == null && Xo(o, a, "");
    for (const a in n)
      a === "display" && (s = !0), Xo(o, a, n[a]);
  } else if (r) {
    if (t !== n) {
      const a = o[xf];
      a && (n += ";" + a), o.cssText = n, s = Cf.test(n);
    }
  } else t && e.removeAttribute("style");
  qa in e && (e[qa] = s ? o.display : "", e[_f] && (o.display = "none"));
}
const Xa = /\s*!important$/;
function Xo(e, t, n) {
  if (re(n))
    n.forEach((o) => Xo(e, t, o));
  else if (n == null && (n = ""), t.startsWith("--"))
    e.setProperty(t, n);
  else {
    const o = $f(e, t);
    Xa.test(n) ? e.setProperty(
      pt(o),
      n.replace(Xa, ""),
      "important"
    ) : e[o] = n;
  }
}
const Ja = ["Webkit", "Moz", "ms"], Gr = {};
function $f(e, t) {
  const n = Gr[t];
  if (n)
    return n;
  let o = qe(t);
  if (o !== "filter" && o in e)
    return Gr[t] = o;
  o = pr(o);
  for (let r = 0; r < Ja.length; r++) {
    const s = Ja[r] + o;
    if (s in e)
      return Gr[t] = s;
  }
  return t;
}
const Za = "http://www.w3.org/1999/xlink";
function Qa(e, t, n, o, r, s = Bc(t)) {
  o && t.startsWith("xlink:") ? n == null ? e.removeAttributeNS(Za, t.slice(6, t.length)) : e.setAttributeNS(Za, t, n) : n == null || s && !zi(n) ? e.removeAttribute(t) : e.setAttribute(
    t,
    s ? "" : tn(n) ? String(n) : n
  );
}
function ei(e, t, n, o, r) {
  if (t === "innerHTML" || t === "textContent") {
    n != null && (e[t] = t === "innerHTML" ? Jl(n) : n);
    return;
  }
  const s = e.tagName;
  if (t === "value" && s !== "PROGRESS" && // custom elements may use _value internally
  !s.includes("-")) {
    const i = s === "OPTION" ? e.getAttribute("value") || "" : e.value, l = n == null ? (
      // #11647: value should be set as empty string for null and undefined,
      // but <input type="checkbox"> should be set as 'on'.
      e.type === "checkbox" ? "on" : ""
    ) : String(n);
    (i !== l || !("_value" in e)) && (e.value = l), n == null && e.removeAttribute(t), e._value = n;
    return;
  }
  let a = !1;
  if (n === "" || n == null) {
    const i = typeof e[t];
    i === "boolean" ? n = zi(n) : n == null && i === "string" ? (n = "", a = !0) : i === "number" && (n = 0, a = !0);
  }
  try {
    e[t] = n;
  } catch {
  }
  a && e.removeAttribute(r || t);
}
function Vn(e, t, n, o) {
  e.addEventListener(t, n, o);
}
function Tf(e, t, n, o) {
  e.removeEventListener(t, n, o);
}
const ti = Symbol("_vei");
function Pf(e, t, n, o, r = null) {
  const s = e[ti] || (e[ti] = {}), a = s[t];
  if (o && a)
    a.value = o;
  else {
    const [i, l] = Ef(t);
    if (o) {
      const u = s[t] = Bf(
        o,
        r
      );
      Vn(e, i, u, l);
    } else a && (Tf(e, i, a, l), s[t] = void 0);
  }
}
const ni = /(?:Once|Passive|Capture)$/;
function Ef(e) {
  let t;
  if (ni.test(e)) {
    t = {};
    let o;
    for (; o = e.match(ni); )
      e = e.slice(0, e.length - o[0].length), t[o[0].toLowerCase()] = !0;
  }
  return [e[2] === ":" ? e.slice(3) : pt(e.slice(2)), t];
}
let Yr = 0;
const Af = /* @__PURE__ */ Promise.resolve(), Of = () => Yr || (Af.then(() => Yr = 0), Yr = Date.now());
function Bf(e, t) {
  const n = (o) => {
    if (!o._vts)
      o._vts = Date.now();
    else if (o._vts <= n.attached)
      return;
    Ft(
      kf(o, n.value),
      t,
      5,
      [o]
    );
  };
  return n.value = e, n.attached = Of(), n;
}
function kf(e, t) {
  if (re(t)) {
    const n = e.stopImmediatePropagation;
    return e.stopImmediatePropagation = () => {
      n.call(e), e._stopped = !0;
    }, t.map(
      (o) => (r) => !r._stopped && o && o(r)
    );
  } else
    return t;
}
const oi = (e) => e.charCodeAt(0) === 111 && e.charCodeAt(1) === 110 && // lowercase letter
e.charCodeAt(2) > 96 && e.charCodeAt(2) < 123, Mf = (e, t, n, o, r, s) => {
  const a = r === "svg";
  t === "class" ? wf(e, o, a) : t === "style" ? Sf(e, n, o) : ur(t) ? Is(t) || Pf(e, t, n, o, s) : (t[0] === "." ? (t = t.slice(1), !0) : t[0] === "^" ? (t = t.slice(1), !1) : Df(e, t, o, a)) ? (ei(e, t, o), !e.tagName.includes("-") && (t === "value" || t === "checked" || t === "selected") && Qa(e, t, o, a, s, t !== "value")) : /* #11081 force set props for possible async custom element */ e._isVueCE && (/[A-Z]/.test(t) || !Fe(o)) ? ei(e, qe(t), o, s, t) : (t === "true-value" ? e._trueValue = o : t === "false-value" && (e._falseValue = o), Qa(e, t, o, a));
};
function Df(e, t, n, o) {
  if (o)
    return !!(t === "innerHTML" || t === "textContent" || t in e && oi(t) && ae(n));
  if (t === "spellcheck" || t === "draggable" || t === "translate" || t === "form" || t === "list" && e.tagName === "INPUT" || t === "type" && e.tagName === "TEXTAREA")
    return !1;
  if (t === "width" || t === "height") {
    const r = e.tagName;
    if (r === "IMG" || r === "VIDEO" || r === "CANVAS" || r === "SOURCE")
      return !1;
  }
  return oi(t) && Fe(n) ? !1 : t in e;
}
const ri = {};
/*! #__NO_SIDE_EFFECTS__ */
// @__NO_SIDE_EFFECTS__
function Rf(e, t, n) {
  const o = /* @__PURE__ */ T(e, t);
  dr(o) && Ke(o, t);
  class r extends qs {
    constructor(a) {
      super(o, a, n);
    }
  }
  return r.def = o, r;
}
const If = typeof HTMLElement < "u" ? HTMLElement : class {
};
class qs extends If {
  constructor(t, n = {}, o = li) {
    super(), this._def = t, this._props = n, this._createApp = o, this._isVueCE = !0, this._instance = null, this._app = null, this._nonce = this._def.nonce, this._connected = !1, this._resolved = !1, this._numberProps = null, this._styleChildren = /* @__PURE__ */ new WeakSet(), this._ob = null, this.shadowRoot && o !== li ? this._root = this.shadowRoot : t.shadowRoot !== !1 ? (this.attachShadow({ mode: "open" }), this._root = this.shadowRoot) : this._root = this, this._def.__asyncLoader || this._resolveProps(this._def);
  }
  connectedCallback() {
    if (!this.isConnected) return;
    this.shadowRoot || this._parseSlots(), this._connected = !0;
    let t = this;
    for (; t = t && (t.parentNode || t.host); )
      if (t instanceof qs) {
        this._parent = t;
        break;
      }
    this._instance || (this._resolved ? (this._setParent(), this._update()) : t && t._pendingResolve ? this._pendingResolve = t._pendingResolve.then(() => {
      this._pendingResolve = void 0, this._resolveDef();
    }) : this._resolveDef());
  }
  _setParent(t = this._parent) {
    t && (this._instance.parent = t._instance, this._instance.provides = t._instance.provides);
  }
  disconnectedCallback() {
    this._connected = !1, Le(() => {
      this._connected || (this._ob && (this._ob.disconnect(), this._ob = null), this._app && this._app.unmount(), this._instance && (this._instance.ce = void 0), this._app = this._instance = null);
    });
  }
  /**
   * resolve inner component definition (handle possible async component)
   */
  _resolveDef() {
    if (this._pendingResolve)
      return;
    for (let o = 0; o < this.attributes.length; o++)
      this._setAttr(this.attributes[o].name);
    this._ob = new MutationObserver((o) => {
      for (const r of o)
        this._setAttr(r.attributeName);
    }), this._ob.observe(this, { attributes: !0 });
    const t = (o, r = !1) => {
      this._resolved = !0, this._pendingResolve = void 0;
      const { props: s, styles: a } = o;
      let i;
      if (s && !re(s))
        for (const l in s) {
          const u = s[l];
          (u === Number || u && u.type === Number) && (l in this._props && (this._props[l] = Pa(this._props[l])), (i || (i = /* @__PURE__ */ Object.create(null)))[qe(l)] = !0);
        }
      this._numberProps = i, r && this._resolveProps(o), this.shadowRoot && this._applyStyles(a), this._mount(o);
    }, n = this._def.__asyncLoader;
    n ? this._pendingResolve = n().then(
      (o) => t(this._def = o, !0)
    ) : t(this._def);
  }
  _mount(t) {
    this._app = this._createApp(t), t.configureApp && t.configureApp(this._app), this._app._ceVNode = this._createVNode(), this._app.mount(this._root);
    const n = this._instance && this._instance.exposed;
    if (n)
      for (const o in n)
        Ce(this, o) || Object.defineProperty(this, o, {
          // unwrap ref to be consistent with public instance behavior
          get: () => d(n[o])
        });
  }
  _resolveProps(t) {
    const { props: n } = t, o = re(n) ? n : Object.keys(n || {});
    for (const r of Object.keys(this))
      r[0] !== "_" && o.includes(r) && this._setProp(r, this[r]);
    for (const r of o.map(qe))
      Object.defineProperty(this, r, {
        get() {
          return this._getProp(r);
        },
        set(s) {
          this._setProp(r, s, !0, !0);
        }
      });
  }
  _setAttr(t) {
    if (t.startsWith("data-v-")) return;
    const n = this.hasAttribute(t);
    let o = n ? this.getAttribute(t) : ri;
    const r = qe(t);
    n && this._numberProps && this._numberProps[r] && (o = Pa(o)), this._setProp(r, o, !1, !0);
  }
  /**
   * @internal
   */
  _getProp(t) {
    return this._props[t];
  }
  /**
   * @internal
   */
  _setProp(t, n, o = !0, r = !1) {
    if (n !== this._props[t] && (n === ri ? delete this._props[t] : (this._props[t] = n, t === "key" && this._app && (this._app._ceVNode.key = n)), r && this._instance && this._update(), o)) {
      const s = this._ob;
      s && s.disconnect(), n === !0 ? this.setAttribute(pt(t), "") : typeof n == "string" || typeof n == "number" ? this.setAttribute(pt(t), n + "") : n || this.removeAttribute(pt(t)), s && s.observe(this, { attributes: !0 });
    }
  }
  _update() {
    Hf(this._createVNode(), this._root);
  }
  _createVNode() {
    const t = {};
    this.shadowRoot || (t.onVnodeMounted = t.onVnodeUpdated = this._renderSlots.bind(this));
    const n = W(this._def, Ke(t, this._props));
    return this._instance || (n.ce = (o) => {
      this._instance = o, o.ce = this, o.isCE = !0;
      const r = (s, a) => {
        this.dispatchEvent(
          new CustomEvent(
            s,
            dr(a[0]) ? Ke({ detail: a }, a[0]) : { detail: a }
          )
        );
      };
      o.emit = (s, ...a) => {
        r(s, a), pt(s) !== s && r(pt(s), a);
      }, this._setParent();
    }), n;
  }
  _applyStyles(t, n) {
    if (!t) return;
    if (n) {
      if (n === this._def || this._styleChildren.has(n))
        return;
      this._styleChildren.add(n);
    }
    const o = this._nonce;
    for (let r = t.length - 1; r >= 0; r--) {
      const s = document.createElement("style");
      o && s.setAttribute("nonce", o), s.textContent = t[r], this.shadowRoot.prepend(s);
    }
  }
  /**
   * Only called when shadowRoot is false
   */
  _parseSlots() {
    const t = this._slots = {};
    let n;
    for (; n = this.firstChild; ) {
      const o = n.nodeType === 1 && n.getAttribute("slot") || "default";
      (t[o] || (t[o] = [])).push(n), this.removeChild(n);
    }
  }
  /**
   * Only called when shadowRoot is false
   */
  _renderSlots() {
    const t = (this._teleportTarget || this).querySelectorAll("slot"), n = this._instance.type.__scopeId;
    for (let o = 0; o < t.length; o++) {
      const r = t[o], s = r.getAttribute("name") || "default", a = this._slots[s], i = r.parentNode;
      if (a)
        for (const l of a) {
          if (n && l.nodeType === 1) {
            const u = n + "-s", c = document.createTreeWalker(l, 1);
            l.setAttribute(u, "");
            let f;
            for (; f = c.nextNode(); )
              f.setAttribute(u, "");
          }
          i.insertBefore(l, r);
        }
      else
        for (; r.firstChild; ) i.insertBefore(r.firstChild, r);
      i.removeChild(r);
    }
  }
  /**
   * @internal
   */
  _injectChildStyle(t) {
    this._applyStyles(t.styles, t);
  }
  /**
   * @internal
   */
  _removeChildStyle(t) {
  }
}
const si = (e) => {
  const t = e.props["onUpdate:modelValue"] || !1;
  return re(t) ? (n) => Uo(t, n) : t;
};
function Lf(e) {
  e.target.composing = !0;
}
function ai(e) {
  const t = e.target;
  t.composing && (t.composing = !1, t.dispatchEvent(new Event("input")));
}
const qr = Symbol("_assign"), Ff = {
  created(e, { modifiers: { lazy: t, trim: n, number: o } }, r) {
    e[qr] = si(r);
    const s = o || r.props && r.props.type === "number";
    Vn(e, t ? "change" : "input", (a) => {
      if (a.target.composing) return;
      let i = e.value;
      n && (i = i.trim()), s && (i = ss(i)), e[qr](i);
    }), n && Vn(e, "change", () => {
      e.value = e.value.trim();
    }), t || (Vn(e, "compositionstart", Lf), Vn(e, "compositionend", ai), Vn(e, "change", ai));
  },
  // set value on mounted so it's after min/max for type="range"
  mounted(e, { value: t }) {
    e.value = t ?? "";
  },
  beforeUpdate(e, { value: t, oldValue: n, modifiers: { lazy: o, trim: r, number: s } }, a) {
    if (e[qr] = si(a), e.composing) return;
    const i = (s || e.type === "number") && !/^0\d/.test(e.value) ? ss(e.value) : e.value, l = t ?? "";
    i !== l && (document.activeElement === e && e.type !== "range" && (o && t === n || r && e.value.trim() === l) || (e.value = l));
  }
}, Vf = ["ctrl", "shift", "alt", "meta"], Nf = {
  stop: (e) => e.stopPropagation(),
  prevent: (e) => e.preventDefault(),
  self: (e) => e.target !== e.currentTarget,
  ctrl: (e) => !e.ctrlKey,
  shift: (e) => !e.shiftKey,
  alt: (e) => !e.altKey,
  meta: (e) => !e.metaKey,
  left: (e) => "button" in e && e.button !== 0,
  middle: (e) => "button" in e && e.button !== 1,
  right: (e) => "button" in e && e.button !== 2,
  exact: (e, t) => Vf.some((n) => e[`${n}Key`] && !t.includes(n))
}, Et = (e, t) => {
  const n = e._withMods || (e._withMods = {}), o = t.join(".");
  return n[o] || (n[o] = (r, ...s) => {
    for (let a = 0; a < t.length; a++) {
      const i = Nf[t[a]];
      if (i && i(r, t)) return;
    }
    return e(r, ...s);
  });
}, zf = {
  esc: "escape",
  space: " ",
  up: "arrow-up",
  left: "arrow-left",
  right: "arrow-right",
  down: "arrow-down",
  delete: "backspace"
}, Xs = (e, t) => {
  const n = e._withKeys || (e._withKeys = {}), o = t.join(".");
  return n[o] || (n[o] = (r) => {
    if (!("key" in r))
      return;
    const s = pt(r.key);
    if (t.some(
      (a) => a === s || zf[a] === s
    ))
      return e(r);
  });
}, jf = /* @__PURE__ */ Ke({ patchProp: Mf }, yf);
let ii;
function Zl() {
  return ii || (ii = Wd(jf));
}
const Hf = (...e) => {
  Zl().render(...e);
}, li = (...e) => {
  const t = Zl().createApp(...e), { mount: n } = t;
  return t.mount = (o) => {
    const r = Kf(o);
    if (!r) return;
    const s = t._component;
    !ae(s) && !s.render && !s.template && (s.template = r.innerHTML), r.nodeType === 1 && (r.textContent = "");
    const a = n(r, !1, Wf(r));
    return r instanceof Element && (r.removeAttribute("v-cloak"), r.setAttribute("data-v-app", "")), a;
  }, t;
};
function Wf(e) {
  if (e instanceof SVGElement)
    return "svg";
  if (typeof MathMLElement == "function" && e instanceof MathMLElement)
    return "mathml";
}
function Kf(e) {
  return Fe(e) ? document.querySelector(e) : e;
}
function Ql(e) {
  var t, n, o = "";
  if (typeof e == "string" || typeof e == "number") o += e;
  else if (typeof e == "object") if (Array.isArray(e)) {
    var r = e.length;
    for (t = 0; t < r; t++) e[t] && (n = Ql(e[t])) && (o && (o += " "), o += n);
  } else for (n in e) e[n] && (o && (o += " "), o += n);
  return o;
}
function eu() {
  for (var e, t, n = 0, o = "", r = arguments.length; n < r; n++) (e = arguments[n]) && (t = Ql(e)) && (o && (o += " "), o += t);
  return o;
}
const ui = (e) => typeof e == "boolean" ? `${e}` : e === 0 ? "0" : e, ci = eu, Bo = (e, t) => (n) => {
  var o;
  if ((t == null ? void 0 : t.variants) == null) return ci(e, n == null ? void 0 : n.class, n == null ? void 0 : n.className);
  const { variants: r, defaultVariants: s } = t, a = Object.keys(r).map((u) => {
    const c = n == null ? void 0 : n[u], f = s == null ? void 0 : s[u];
    if (c === null) return null;
    const p = ui(c) || ui(f);
    return r[u][p];
  }), i = n && Object.entries(n).reduce((u, c) => {
    let [f, p] = c;
    return p === void 0 || (u[f] = p), u;
  }, {}), l = t == null || (o = t.compoundVariants) === null || o === void 0 ? void 0 : o.reduce((u, c) => {
    let { class: f, className: p, ...h } = c;
    return Object.entries(h).every((g) => {
      let [y, C] = g;
      return Array.isArray(C) ? C.includes({
        ...s,
        ...i
      }[y]) : {
        ...s,
        ...i
      }[y] === C;
    }) ? [
      ...u,
      f,
      p
    ] : u;
  }, []);
  return ci(e, a, l, n == null ? void 0 : n.class, n == null ? void 0 : n.className);
}, Uf = Bo(
  "inline-flex items-center rounded-full font-semibold leading-none transition-all duration-200 ease-in-out unraid-ui-badge-test",
  {
    variants: {
      variant: {
        red: "bg-unraid-red text-white hover:bg-orange-dark",
        yellow: "bg-yellow-100 text-black hover:bg-yellow-200",
        green: "bg-green-200 text-green-800 hover:bg-green-300",
        blue: "bg-blue-100 text-blue-800 hover:bg-blue-200",
        indigo: "bg-indigo-100 text-indigo-800 hover:bg-indigo-200",
        purple: "bg-purple-100 text-purple-800 hover:bg-purple-200",
        pink: "bg-pink-100 text-pink-800 hover:bg-pink-200",
        orange: "bg-orange text-white hover:bg-orange-dark",
        black: "bg-black text-white hover:bg-gray-800",
        white: "bg-white text-black hover:bg-gray-100",
        transparent: "bg-transparent text-black hover:bg-gray-100",
        current: "bg-current text-current hover:bg-gray-100",
        gray: "bg-gray-200 text-gray-800 hover:bg-gray-300",
        custom: ""
      },
      size: {
        xs: "text-12px px-8px py-4px gap-4px",
        sm: "text-14px px-8px py-4px gap-8px",
        md: "text-16px px-12px py-8px gap-8px",
        lg: "text-18px px-12px py-8px gap-8px",
        xl: "text-20px px-16px py-12px gap-8px",
        "2xl": "text-24px px-16px py-12px gap-8px"
      }
    },
    defaultVariants: {
      variant: "gray",
      size: "md"
    }
  }
), Gf = /* @__PURE__ */ T({
  __name: "Badge",
  props: {
    variant: { default: "gray" },
    size: { default: "md" },
    icon: { default: void 0 },
    iconRight: { default: void 0 },
    iconStyles: { default: "" },
    class: { default: "" }
  },
  setup(e) {
    const t = e, n = O(() => {
      const o = {
        xs: "w-12px",
        sm: "w-14px",
        md: "w-16px",
        lg: "w-18px",
        xl: "w-20px",
        "2xl": "w-24px"
      };
      return {
        badge: Uf({ variant: t.variant, size: t.size }),
        icon: `${o[t.size ?? "md"]} ${t.iconStyles}`
      };
    });
    return (o, r) => (_(), oe("span", {
      class: le([n.value.badge, t.class])
    }, [
      o.icon ? (_(), E(_t(o.icon), {
        key: 0,
        class: le(["flex-shrink-0", n.value.icon])
      }, null, 8, ["class"])) : Be("", !0),
      P(o.$slots, "default"),
      o.iconRight ? (_(), E(_t(o.iconRight), {
        key: 1,
        class: le(["flex-shrink-0", n.value.icon])
      }, null, 8, ["class"])) : Be("", !0)
    ], 2));
  }
}), Yf = Bo(
  "group text-center font-semibold leading-none relative z-0 flex flex-row items-center justify-center border-2 border-solid shadow-none cursor-pointer rounded-md hover:shadow-md focus:shadow-md disabled:opacity-25 disabled:hover:opacity-25 disabled:focus:opacity-25 disabled:cursor-not-allowed",
  {
    variants: {
      variant: {
        fill: "[&]:text-white bg-transparent border-transparent",
        black: "[&]:text-white bg-black border-black transition hover:text-black focus:text-black hover:bg-grey focus:bg-grey hover:border-grey focus:border-grey",
        gray: "text-black bg-grey transition hover:text-white focus:text-white hover:bg-grey-mid focus:bg-grey-mid hover:border-grey-mid focus:border-grey-mid",
        outline: "[&]:text-orange bg-transparent border-orange hover:text-white focus:text-white",
        "outline-primary": "text-primary [&]:text-primary uppercase tracking-widest bg-transparent border-primary rounded-sm hover:text-white focus:text-white",
        "outline-black": "text-black bg-transparent border-black hover:text-black focus:text-black hover:bg-grey focus:bg-grey hover:border-grey focus:border-grey",
        "outline-white": "text-white bg-transparent border-white hover:text-black focus:text-black hover:bg-white focus:bg-white",
        underline: "opacity-75 underline border-transparent transition hover:text-primary hover:bg-muted hover:border-muted focus:text-primary focus:bg-muted focus:border-muted hover:opacity-100 focus:opacity-100",
        "underline-hover-red": "opacity-75 underline border-transparent transition hover:text-white hover:bg-unraid-red hover:border-unraid-red focus:text-white focus:bg-unraid-red focus:border-unraid-red hover:opacity-100 focus:opacity-100",
        white: "text-black bg-white transition hover:bg-grey focus:bg-grey",
        none: ""
      },
      size: {
        "12px": "text-12px gap-4px",
        "14px": "text-14px gap-8px",
        "16px": "text-16px gap-8px",
        "18px": "text-18px gap-8px",
        "20px": "text-20px gap-8px",
        "24px": "text-24px gap-8px"
      },
      padding: {
        default: "",
        none: "p-0",
        lean: "px-4 py-2"
      }
    },
    compoundVariants: [
      {
        size: "12px",
        padding: "default",
        class: "p-8px"
      },
      {
        size: "14px",
        padding: "default",
        class: "p-8px"
      },
      {
        size: "16px",
        padding: "default",
        class: "p-12px"
      },
      {
        size: "18px",
        padding: "default",
        class: "p-12px"
      },
      {
        size: "20px",
        padding: "default",
        class: "p-16px"
      },
      {
        size: "24px",
        padding: "default",
        class: "p-16px"
      }
    ],
    defaultVariants: {
      variant: "fill",
      size: "16px",
      padding: "default"
    }
  }
), Js = "-", qf = (e) => {
  const t = Jf(e), {
    conflictingClassGroups: n,
    conflictingClassGroupModifiers: o
  } = e;
  return {
    getClassGroupId: (a) => {
      const i = a.split(Js);
      return i[0] === "" && i.length !== 1 && i.shift(), tu(i, t) || Xf(a);
    },
    getConflictingClassGroupIds: (a, i) => {
      const l = n[a] || [];
      return i && o[a] ? [...l, ...o[a]] : l;
    }
  };
}, tu = (e, t) => {
  var a;
  if (e.length === 0)
    return t.classGroupId;
  const n = e[0], o = t.nextPart.get(n), r = o ? tu(e.slice(1), o) : void 0;
  if (r)
    return r;
  if (t.validators.length === 0)
    return;
  const s = e.join(Js);
  return (a = t.validators.find(({
    validator: i
  }) => i(s))) == null ? void 0 : a.classGroupId;
}, di = /^\[(.+)\]$/, Xf = (e) => {
  if (di.test(e)) {
    const t = di.exec(e)[1], n = t == null ? void 0 : t.substring(0, t.indexOf(":"));
    if (n)
      return "arbitrary.." + n;
  }
}, Jf = (e) => {
  const {
    theme: t,
    prefix: n
  } = e, o = {
    nextPart: /* @__PURE__ */ new Map(),
    validators: []
  };
  return Qf(Object.entries(e.classGroups), n).forEach(([s, a]) => {
    bs(a, o, s, t);
  }), o;
}, bs = (e, t, n, o) => {
  e.forEach((r) => {
    if (typeof r == "string") {
      const s = r === "" ? t : fi(t, r);
      s.classGroupId = n;
      return;
    }
    if (typeof r == "function") {
      if (Zf(r)) {
        bs(r(o), t, n, o);
        return;
      }
      t.validators.push({
        validator: r,
        classGroupId: n
      });
      return;
    }
    Object.entries(r).forEach(([s, a]) => {
      bs(a, fi(t, s), n, o);
    });
  });
}, fi = (e, t) => {
  let n = e;
  return t.split(Js).forEach((o) => {
    n.nextPart.has(o) || n.nextPart.set(o, {
      nextPart: /* @__PURE__ */ new Map(),
      validators: []
    }), n = n.nextPart.get(o);
  }), n;
}, Zf = (e) => e.isThemeGetter, Qf = (e, t) => t ? e.map(([n, o]) => {
  const r = o.map((s) => typeof s == "string" ? t + s : typeof s == "object" ? Object.fromEntries(Object.entries(s).map(([a, i]) => [t + a, i])) : s);
  return [n, r];
}) : e, ep = (e) => {
  if (e < 1)
    return {
      get: () => {
      },
      set: () => {
      }
    };
  let t = 0, n = /* @__PURE__ */ new Map(), o = /* @__PURE__ */ new Map();
  const r = (s, a) => {
    n.set(s, a), t++, t > e && (t = 0, o = n, n = /* @__PURE__ */ new Map());
  };
  return {
    get(s) {
      let a = n.get(s);
      if (a !== void 0)
        return a;
      if ((a = o.get(s)) !== void 0)
        return r(s, a), a;
    },
    set(s, a) {
      n.has(s) ? n.set(s, a) : r(s, a);
    }
  };
}, nu = "!", tp = (e) => {
  const {
    separator: t,
    experimentalParseClassName: n
  } = e, o = t.length === 1, r = t[0], s = t.length, a = (i) => {
    const l = [];
    let u = 0, c = 0, f;
    for (let C = 0; C < i.length; C++) {
      let v = i[C];
      if (u === 0) {
        if (v === r && (o || i.slice(C, C + s) === t)) {
          l.push(i.slice(c, C)), c = C + s;
          continue;
        }
        if (v === "/") {
          f = C;
          continue;
        }
      }
      v === "[" ? u++ : v === "]" && u--;
    }
    const p = l.length === 0 ? i : i.substring(c), h = p.startsWith(nu), g = h ? p.substring(1) : p, y = f && f > c ? f - c : void 0;
    return {
      modifiers: l,
      hasImportantModifier: h,
      baseClassName: g,
      maybePostfixModifierPosition: y
    };
  };
  return n ? (i) => n({
    className: i,
    parseClassName: a
  }) : a;
}, np = (e) => {
  if (e.length <= 1)
    return e;
  const t = [];
  let n = [];
  return e.forEach((o) => {
    o[0] === "[" ? (t.push(...n.sort(), o), n = []) : n.push(o);
  }), t.push(...n.sort()), t;
}, op = (e) => ({
  cache: ep(e.cacheSize),
  parseClassName: tp(e),
  ...qf(e)
}), rp = /\s+/, sp = (e, t) => {
  const {
    parseClassName: n,
    getClassGroupId: o,
    getConflictingClassGroupIds: r
  } = t, s = [], a = e.trim().split(rp);
  let i = "";
  for (let l = a.length - 1; l >= 0; l -= 1) {
    const u = a[l], {
      modifiers: c,
      hasImportantModifier: f,
      baseClassName: p,
      maybePostfixModifierPosition: h
    } = n(u);
    let g = !!h, y = o(g ? p.substring(0, h) : p);
    if (!y) {
      if (!g) {
        i = u + (i.length > 0 ? " " + i : i);
        continue;
      }
      if (y = o(p), !y) {
        i = u + (i.length > 0 ? " " + i : i);
        continue;
      }
      g = !1;
    }
    const C = np(c).join(":"), v = f ? C + nu : C, w = v + y;
    if (s.includes(w))
      continue;
    s.push(w);
    const m = r(y, g);
    for (let x = 0; x < m.length; ++x) {
      const A = m[x];
      s.push(v + A);
    }
    i = u + (i.length > 0 ? " " + i : i);
  }
  return i;
};
function ap() {
  let e = 0, t, n, o = "";
  for (; e < arguments.length; )
    (t = arguments[e++]) && (n = ou(t)) && (o && (o += " "), o += n);
  return o;
}
const ou = (e) => {
  if (typeof e == "string")
    return e;
  let t, n = "";
  for (let o = 0; o < e.length; o++)
    e[o] && (t = ou(e[o])) && (n && (n += " "), n += t);
  return n;
};
function ip(e, ...t) {
  let n, o, r, s = a;
  function a(l) {
    const u = t.reduce((c, f) => f(c), e());
    return n = op(u), o = n.cache.get, r = n.cache.set, s = i, i(l);
  }
  function i(l) {
    const u = o(l);
    if (u)
      return u;
    const c = sp(l, n);
    return r(l, c), c;
  }
  return function() {
    return s(ap.apply(null, arguments));
  };
}
const Re = (e) => {
  const t = (n) => n[e] || [];
  return t.isThemeGetter = !0, t;
}, ru = /^\[(?:([a-z-]+):)?(.+)\]$/i, lp = /^\d+\/\d+$/, up = /* @__PURE__ */ new Set(["px", "full", "screen"]), cp = /^(\d+(\.\d+)?)?(xs|sm|md|lg|xl)$/, dp = /\d+(%|px|r?em|[sdl]?v([hwib]|min|max)|pt|pc|in|cm|mm|cap|ch|ex|r?lh|cq(w|h|i|b|min|max))|\b(calc|min|max|clamp)\(.+\)|^0$/, fp = /^(rgba?|hsla?|hwb|(ok)?(lab|lch))\(.+\)$/, pp = /^(inset_)?-?((\d+)?\.?(\d+)[a-z]+|0)_-?((\d+)?\.?(\d+)[a-z]+|0)/, hp = /^(url|image|image-set|cross-fade|element|(repeating-)?(linear|radial|conic)-gradient)\(.+\)$/, Gt = (e) => Gn(e) || up.has(e) || lp.test(e), un = (e) => Zn(e, "length", xp), Gn = (e) => !!e && !Number.isNaN(Number(e)), Xr = (e) => Zn(e, "number", Gn), ao = (e) => !!e && Number.isInteger(Number(e)), mp = (e) => e.endsWith("%") && Gn(e.slice(0, -1)), de = (e) => ru.test(e), cn = (e) => cp.test(e), gp = /* @__PURE__ */ new Set(["length", "size", "percentage"]), vp = (e) => Zn(e, gp, su), yp = (e) => Zn(e, "position", su), bp = /* @__PURE__ */ new Set(["image", "url"]), wp = (e) => Zn(e, bp, Sp), _p = (e) => Zn(e, "", Cp), io = () => !0, Zn = (e, t, n) => {
  const o = ru.exec(e);
  return o ? o[1] ? typeof t == "string" ? o[1] === t : t.has(o[1]) : n(o[2]) : !1;
}, xp = (e) => (
  // `colorFunctionRegex` check is necessary because color functions can have percentages in them which which would be incorrectly classified as lengths.
  // For example, `hsl(0 0% 0%)` would be classified as a length without this check.
  // I could also use lookbehind assertion in `lengthUnitRegex` but that isn't supported widely enough.
  dp.test(e) && !fp.test(e)
), su = () => !1, Cp = (e) => pp.test(e), Sp = (e) => hp.test(e), $p = () => {
  const e = Re("colors"), t = Re("spacing"), n = Re("blur"), o = Re("brightness"), r = Re("borderColor"), s = Re("borderRadius"), a = Re("borderSpacing"), i = Re("borderWidth"), l = Re("contrast"), u = Re("grayscale"), c = Re("hueRotate"), f = Re("invert"), p = Re("gap"), h = Re("gradientColorStops"), g = Re("gradientColorStopPositions"), y = Re("inset"), C = Re("margin"), v = Re("opacity"), w = Re("padding"), m = Re("saturate"), x = Re("scale"), A = Re("sepia"), R = Re("skew"), I = Re("space"), H = Re("translate"), k = () => ["auto", "contain", "none"], j = () => ["auto", "hidden", "clip", "visible", "scroll"], K = () => ["auto", de, t], J = () => [de, t], ge = () => ["", Gt, un], ce = () => ["auto", Gn, de], Pe = () => ["bottom", "center", "left", "left-bottom", "left-top", "right", "right-bottom", "right-top", "top"], ue = () => ["solid", "dashed", "dotted", "double", "none"], ee = () => ["normal", "multiply", "screen", "overlay", "darken", "lighten", "color-dodge", "color-burn", "hard-light", "soft-light", "difference", "exclusion", "hue", "saturation", "color", "luminosity"], ne = () => ["start", "end", "center", "between", "around", "evenly", "stretch"], he = () => ["", "0", de], je = () => ["auto", "avoid", "all", "avoid-page", "page", "left", "right", "column"], ze = () => [Gn, de];
  return {
    cacheSize: 500,
    separator: ":",
    theme: {
      colors: [io],
      spacing: [Gt, un],
      blur: ["none", "", cn, de],
      brightness: ze(),
      borderColor: [e],
      borderRadius: ["none", "", "full", cn, de],
      borderSpacing: J(),
      borderWidth: ge(),
      contrast: ze(),
      grayscale: he(),
      hueRotate: ze(),
      invert: he(),
      gap: J(),
      gradientColorStops: [e],
      gradientColorStopPositions: [mp, un],
      inset: K(),
      margin: K(),
      opacity: ze(),
      padding: J(),
      saturate: ze(),
      scale: ze(),
      sepia: he(),
      skew: ze(),
      space: J(),
      translate: J()
    },
    classGroups: {
      // Layout
      /**
       * Aspect Ratio
       * @see https://tailwindcss.com/docs/aspect-ratio
       */
      aspect: [{
        aspect: ["auto", "square", "video", de]
      }],
      /**
       * Container
       * @see https://tailwindcss.com/docs/container
       */
      container: ["container"],
      /**
       * Columns
       * @see https://tailwindcss.com/docs/columns
       */
      columns: [{
        columns: [cn]
      }],
      /**
       * Break After
       * @see https://tailwindcss.com/docs/break-after
       */
      "break-after": [{
        "break-after": je()
      }],
      /**
       * Break Before
       * @see https://tailwindcss.com/docs/break-before
       */
      "break-before": [{
        "break-before": je()
      }],
      /**
       * Break Inside
       * @see https://tailwindcss.com/docs/break-inside
       */
      "break-inside": [{
        "break-inside": ["auto", "avoid", "avoid-page", "avoid-column"]
      }],
      /**
       * Box Decoration Break
       * @see https://tailwindcss.com/docs/box-decoration-break
       */
      "box-decoration": [{
        "box-decoration": ["slice", "clone"]
      }],
      /**
       * Box Sizing
       * @see https://tailwindcss.com/docs/box-sizing
       */
      box: [{
        box: ["border", "content"]
      }],
      /**
       * Display
       * @see https://tailwindcss.com/docs/display
       */
      display: ["block", "inline-block", "inline", "flex", "inline-flex", "table", "inline-table", "table-caption", "table-cell", "table-column", "table-column-group", "table-footer-group", "table-header-group", "table-row-group", "table-row", "flow-root", "grid", "inline-grid", "contents", "list-item", "hidden"],
      /**
       * Floats
       * @see https://tailwindcss.com/docs/float
       */
      float: [{
        float: ["right", "left", "none", "start", "end"]
      }],
      /**
       * Clear
       * @see https://tailwindcss.com/docs/clear
       */
      clear: [{
        clear: ["left", "right", "both", "none", "start", "end"]
      }],
      /**
       * Isolation
       * @see https://tailwindcss.com/docs/isolation
       */
      isolation: ["isolate", "isolation-auto"],
      /**
       * Object Fit
       * @see https://tailwindcss.com/docs/object-fit
       */
      "object-fit": [{
        object: ["contain", "cover", "fill", "none", "scale-down"]
      }],
      /**
       * Object Position
       * @see https://tailwindcss.com/docs/object-position
       */
      "object-position": [{
        object: [...Pe(), de]
      }],
      /**
       * Overflow
       * @see https://tailwindcss.com/docs/overflow
       */
      overflow: [{
        overflow: j()
      }],
      /**
       * Overflow X
       * @see https://tailwindcss.com/docs/overflow
       */
      "overflow-x": [{
        "overflow-x": j()
      }],
      /**
       * Overflow Y
       * @see https://tailwindcss.com/docs/overflow
       */
      "overflow-y": [{
        "overflow-y": j()
      }],
      /**
       * Overscroll Behavior
       * @see https://tailwindcss.com/docs/overscroll-behavior
       */
      overscroll: [{
        overscroll: k()
      }],
      /**
       * Overscroll Behavior X
       * @see https://tailwindcss.com/docs/overscroll-behavior
       */
      "overscroll-x": [{
        "overscroll-x": k()
      }],
      /**
       * Overscroll Behavior Y
       * @see https://tailwindcss.com/docs/overscroll-behavior
       */
      "overscroll-y": [{
        "overscroll-y": k()
      }],
      /**
       * Position
       * @see https://tailwindcss.com/docs/position
       */
      position: ["static", "fixed", "absolute", "relative", "sticky"],
      /**
       * Top / Right / Bottom / Left
       * @see https://tailwindcss.com/docs/top-right-bottom-left
       */
      inset: [{
        inset: [y]
      }],
      /**
       * Right / Left
       * @see https://tailwindcss.com/docs/top-right-bottom-left
       */
      "inset-x": [{
        "inset-x": [y]
      }],
      /**
       * Top / Bottom
       * @see https://tailwindcss.com/docs/top-right-bottom-left
       */
      "inset-y": [{
        "inset-y": [y]
      }],
      /**
       * Start
       * @see https://tailwindcss.com/docs/top-right-bottom-left
       */
      start: [{
        start: [y]
      }],
      /**
       * End
       * @see https://tailwindcss.com/docs/top-right-bottom-left
       */
      end: [{
        end: [y]
      }],
      /**
       * Top
       * @see https://tailwindcss.com/docs/top-right-bottom-left
       */
      top: [{
        top: [y]
      }],
      /**
       * Right
       * @see https://tailwindcss.com/docs/top-right-bottom-left
       */
      right: [{
        right: [y]
      }],
      /**
       * Bottom
       * @see https://tailwindcss.com/docs/top-right-bottom-left
       */
      bottom: [{
        bottom: [y]
      }],
      /**
       * Left
       * @see https://tailwindcss.com/docs/top-right-bottom-left
       */
      left: [{
        left: [y]
      }],
      /**
       * Visibility
       * @see https://tailwindcss.com/docs/visibility
       */
      visibility: ["visible", "invisible", "collapse"],
      /**
       * Z-Index
       * @see https://tailwindcss.com/docs/z-index
       */
      z: [{
        z: ["auto", ao, de]
      }],
      // Flexbox and Grid
      /**
       * Flex Basis
       * @see https://tailwindcss.com/docs/flex-basis
       */
      basis: [{
        basis: K()
      }],
      /**
       * Flex Direction
       * @see https://tailwindcss.com/docs/flex-direction
       */
      "flex-direction": [{
        flex: ["row", "row-reverse", "col", "col-reverse"]
      }],
      /**
       * Flex Wrap
       * @see https://tailwindcss.com/docs/flex-wrap
       */
      "flex-wrap": [{
        flex: ["wrap", "wrap-reverse", "nowrap"]
      }],
      /**
       * Flex
       * @see https://tailwindcss.com/docs/flex
       */
      flex: [{
        flex: ["1", "auto", "initial", "none", de]
      }],
      /**
       * Flex Grow
       * @see https://tailwindcss.com/docs/flex-grow
       */
      grow: [{
        grow: he()
      }],
      /**
       * Flex Shrink
       * @see https://tailwindcss.com/docs/flex-shrink
       */
      shrink: [{
        shrink: he()
      }],
      /**
       * Order
       * @see https://tailwindcss.com/docs/order
       */
      order: [{
        order: ["first", "last", "none", ao, de]
      }],
      /**
       * Grid Template Columns
       * @see https://tailwindcss.com/docs/grid-template-columns
       */
      "grid-cols": [{
        "grid-cols": [io]
      }],
      /**
       * Grid Column Start / End
       * @see https://tailwindcss.com/docs/grid-column
       */
      "col-start-end": [{
        col: ["auto", {
          span: ["full", ao, de]
        }, de]
      }],
      /**
       * Grid Column Start
       * @see https://tailwindcss.com/docs/grid-column
       */
      "col-start": [{
        "col-start": ce()
      }],
      /**
       * Grid Column End
       * @see https://tailwindcss.com/docs/grid-column
       */
      "col-end": [{
        "col-end": ce()
      }],
      /**
       * Grid Template Rows
       * @see https://tailwindcss.com/docs/grid-template-rows
       */
      "grid-rows": [{
        "grid-rows": [io]
      }],
      /**
       * Grid Row Start / End
       * @see https://tailwindcss.com/docs/grid-row
       */
      "row-start-end": [{
        row: ["auto", {
          span: [ao, de]
        }, de]
      }],
      /**
       * Grid Row Start
       * @see https://tailwindcss.com/docs/grid-row
       */
      "row-start": [{
        "row-start": ce()
      }],
      /**
       * Grid Row End
       * @see https://tailwindcss.com/docs/grid-row
       */
      "row-end": [{
        "row-end": ce()
      }],
      /**
       * Grid Auto Flow
       * @see https://tailwindcss.com/docs/grid-auto-flow
       */
      "grid-flow": [{
        "grid-flow": ["row", "col", "dense", "row-dense", "col-dense"]
      }],
      /**
       * Grid Auto Columns
       * @see https://tailwindcss.com/docs/grid-auto-columns
       */
      "auto-cols": [{
        "auto-cols": ["auto", "min", "max", "fr", de]
      }],
      /**
       * Grid Auto Rows
       * @see https://tailwindcss.com/docs/grid-auto-rows
       */
      "auto-rows": [{
        "auto-rows": ["auto", "min", "max", "fr", de]
      }],
      /**
       * Gap
       * @see https://tailwindcss.com/docs/gap
       */
      gap: [{
        gap: [p]
      }],
      /**
       * Gap X
       * @see https://tailwindcss.com/docs/gap
       */
      "gap-x": [{
        "gap-x": [p]
      }],
      /**
       * Gap Y
       * @see https://tailwindcss.com/docs/gap
       */
      "gap-y": [{
        "gap-y": [p]
      }],
      /**
       * Justify Content
       * @see https://tailwindcss.com/docs/justify-content
       */
      "justify-content": [{
        justify: ["normal", ...ne()]
      }],
      /**
       * Justify Items
       * @see https://tailwindcss.com/docs/justify-items
       */
      "justify-items": [{
        "justify-items": ["start", "end", "center", "stretch"]
      }],
      /**
       * Justify Self
       * @see https://tailwindcss.com/docs/justify-self
       */
      "justify-self": [{
        "justify-self": ["auto", "start", "end", "center", "stretch"]
      }],
      /**
       * Align Content
       * @see https://tailwindcss.com/docs/align-content
       */
      "align-content": [{
        content: ["normal", ...ne(), "baseline"]
      }],
      /**
       * Align Items
       * @see https://tailwindcss.com/docs/align-items
       */
      "align-items": [{
        items: ["start", "end", "center", "baseline", "stretch"]
      }],
      /**
       * Align Self
       * @see https://tailwindcss.com/docs/align-self
       */
      "align-self": [{
        self: ["auto", "start", "end", "center", "stretch", "baseline"]
      }],
      /**
       * Place Content
       * @see https://tailwindcss.com/docs/place-content
       */
      "place-content": [{
        "place-content": [...ne(), "baseline"]
      }],
      /**
       * Place Items
       * @see https://tailwindcss.com/docs/place-items
       */
      "place-items": [{
        "place-items": ["start", "end", "center", "baseline", "stretch"]
      }],
      /**
       * Place Self
       * @see https://tailwindcss.com/docs/place-self
       */
      "place-self": [{
        "place-self": ["auto", "start", "end", "center", "stretch"]
      }],
      // Spacing
      /**
       * Padding
       * @see https://tailwindcss.com/docs/padding
       */
      p: [{
        p: [w]
      }],
      /**
       * Padding X
       * @see https://tailwindcss.com/docs/padding
       */
      px: [{
        px: [w]
      }],
      /**
       * Padding Y
       * @see https://tailwindcss.com/docs/padding
       */
      py: [{
        py: [w]
      }],
      /**
       * Padding Start
       * @see https://tailwindcss.com/docs/padding
       */
      ps: [{
        ps: [w]
      }],
      /**
       * Padding End
       * @see https://tailwindcss.com/docs/padding
       */
      pe: [{
        pe: [w]
      }],
      /**
       * Padding Top
       * @see https://tailwindcss.com/docs/padding
       */
      pt: [{
        pt: [w]
      }],
      /**
       * Padding Right
       * @see https://tailwindcss.com/docs/padding
       */
      pr: [{
        pr: [w]
      }],
      /**
       * Padding Bottom
       * @see https://tailwindcss.com/docs/padding
       */
      pb: [{
        pb: [w]
      }],
      /**
       * Padding Left
       * @see https://tailwindcss.com/docs/padding
       */
      pl: [{
        pl: [w]
      }],
      /**
       * Margin
       * @see https://tailwindcss.com/docs/margin
       */
      m: [{
        m: [C]
      }],
      /**
       * Margin X
       * @see https://tailwindcss.com/docs/margin
       */
      mx: [{
        mx: [C]
      }],
      /**
       * Margin Y
       * @see https://tailwindcss.com/docs/margin
       */
      my: [{
        my: [C]
      }],
      /**
       * Margin Start
       * @see https://tailwindcss.com/docs/margin
       */
      ms: [{
        ms: [C]
      }],
      /**
       * Margin End
       * @see https://tailwindcss.com/docs/margin
       */
      me: [{
        me: [C]
      }],
      /**
       * Margin Top
       * @see https://tailwindcss.com/docs/margin
       */
      mt: [{
        mt: [C]
      }],
      /**
       * Margin Right
       * @see https://tailwindcss.com/docs/margin
       */
      mr: [{
        mr: [C]
      }],
      /**
       * Margin Bottom
       * @see https://tailwindcss.com/docs/margin
       */
      mb: [{
        mb: [C]
      }],
      /**
       * Margin Left
       * @see https://tailwindcss.com/docs/margin
       */
      ml: [{
        ml: [C]
      }],
      /**
       * Space Between X
       * @see https://tailwindcss.com/docs/space
       */
      "space-x": [{
        "space-x": [I]
      }],
      /**
       * Space Between X Reverse
       * @see https://tailwindcss.com/docs/space
       */
      "space-x-reverse": ["space-x-reverse"],
      /**
       * Space Between Y
       * @see https://tailwindcss.com/docs/space
       */
      "space-y": [{
        "space-y": [I]
      }],
      /**
       * Space Between Y Reverse
       * @see https://tailwindcss.com/docs/space
       */
      "space-y-reverse": ["space-y-reverse"],
      // Sizing
      /**
       * Width
       * @see https://tailwindcss.com/docs/width
       */
      w: [{
        w: ["auto", "min", "max", "fit", "svw", "lvw", "dvw", de, t]
      }],
      /**
       * Min-Width
       * @see https://tailwindcss.com/docs/min-width
       */
      "min-w": [{
        "min-w": [de, t, "min", "max", "fit"]
      }],
      /**
       * Max-Width
       * @see https://tailwindcss.com/docs/max-width
       */
      "max-w": [{
        "max-w": [de, t, "none", "full", "min", "max", "fit", "prose", {
          screen: [cn]
        }, cn]
      }],
      /**
       * Height
       * @see https://tailwindcss.com/docs/height
       */
      h: [{
        h: [de, t, "auto", "min", "max", "fit", "svh", "lvh", "dvh"]
      }],
      /**
       * Min-Height
       * @see https://tailwindcss.com/docs/min-height
       */
      "min-h": [{
        "min-h": [de, t, "min", "max", "fit", "svh", "lvh", "dvh"]
      }],
      /**
       * Max-Height
       * @see https://tailwindcss.com/docs/max-height
       */
      "max-h": [{
        "max-h": [de, t, "min", "max", "fit", "svh", "lvh", "dvh"]
      }],
      /**
       * Size
       * @see https://tailwindcss.com/docs/size
       */
      size: [{
        size: [de, t, "auto", "min", "max", "fit"]
      }],
      // Typography
      /**
       * Font Size
       * @see https://tailwindcss.com/docs/font-size
       */
      "font-size": [{
        text: ["base", cn, un]
      }],
      /**
       * Font Smoothing
       * @see https://tailwindcss.com/docs/font-smoothing
       */
      "font-smoothing": ["antialiased", "subpixel-antialiased"],
      /**
       * Font Style
       * @see https://tailwindcss.com/docs/font-style
       */
      "font-style": ["italic", "not-italic"],
      /**
       * Font Weight
       * @see https://tailwindcss.com/docs/font-weight
       */
      "font-weight": [{
        font: ["thin", "extralight", "light", "normal", "medium", "semibold", "bold", "extrabold", "black", Xr]
      }],
      /**
       * Font Family
       * @see https://tailwindcss.com/docs/font-family
       */
      "font-family": [{
        font: [io]
      }],
      /**
       * Font Variant Numeric
       * @see https://tailwindcss.com/docs/font-variant-numeric
       */
      "fvn-normal": ["normal-nums"],
      /**
       * Font Variant Numeric
       * @see https://tailwindcss.com/docs/font-variant-numeric
       */
      "fvn-ordinal": ["ordinal"],
      /**
       * Font Variant Numeric
       * @see https://tailwindcss.com/docs/font-variant-numeric
       */
      "fvn-slashed-zero": ["slashed-zero"],
      /**
       * Font Variant Numeric
       * @see https://tailwindcss.com/docs/font-variant-numeric
       */
      "fvn-figure": ["lining-nums", "oldstyle-nums"],
      /**
       * Font Variant Numeric
       * @see https://tailwindcss.com/docs/font-variant-numeric
       */
      "fvn-spacing": ["proportional-nums", "tabular-nums"],
      /**
       * Font Variant Numeric
       * @see https://tailwindcss.com/docs/font-variant-numeric
       */
      "fvn-fraction": ["diagonal-fractions", "stacked-fractions"],
      /**
       * Letter Spacing
       * @see https://tailwindcss.com/docs/letter-spacing
       */
      tracking: [{
        tracking: ["tighter", "tight", "normal", "wide", "wider", "widest", de]
      }],
      /**
       * Line Clamp
       * @see https://tailwindcss.com/docs/line-clamp
       */
      "line-clamp": [{
        "line-clamp": ["none", Gn, Xr]
      }],
      /**
       * Line Height
       * @see https://tailwindcss.com/docs/line-height
       */
      leading: [{
        leading: ["none", "tight", "snug", "normal", "relaxed", "loose", Gt, de]
      }],
      /**
       * List Style Image
       * @see https://tailwindcss.com/docs/list-style-image
       */
      "list-image": [{
        "list-image": ["none", de]
      }],
      /**
       * List Style Type
       * @see https://tailwindcss.com/docs/list-style-type
       */
      "list-style-type": [{
        list: ["none", "disc", "decimal", de]
      }],
      /**
       * List Style Position
       * @see https://tailwindcss.com/docs/list-style-position
       */
      "list-style-position": [{
        list: ["inside", "outside"]
      }],
      /**
       * Placeholder Color
       * @deprecated since Tailwind CSS v3.0.0
       * @see https://tailwindcss.com/docs/placeholder-color
       */
      "placeholder-color": [{
        placeholder: [e]
      }],
      /**
       * Placeholder Opacity
       * @see https://tailwindcss.com/docs/placeholder-opacity
       */
      "placeholder-opacity": [{
        "placeholder-opacity": [v]
      }],
      /**
       * Text Alignment
       * @see https://tailwindcss.com/docs/text-align
       */
      "text-alignment": [{
        text: ["left", "center", "right", "justify", "start", "end"]
      }],
      /**
       * Text Color
       * @see https://tailwindcss.com/docs/text-color
       */
      "text-color": [{
        text: [e]
      }],
      /**
       * Text Opacity
       * @see https://tailwindcss.com/docs/text-opacity
       */
      "text-opacity": [{
        "text-opacity": [v]
      }],
      /**
       * Text Decoration
       * @see https://tailwindcss.com/docs/text-decoration
       */
      "text-decoration": ["underline", "overline", "line-through", "no-underline"],
      /**
       * Text Decoration Style
       * @see https://tailwindcss.com/docs/text-decoration-style
       */
      "text-decoration-style": [{
        decoration: [...ue(), "wavy"]
      }],
      /**
       * Text Decoration Thickness
       * @see https://tailwindcss.com/docs/text-decoration-thickness
       */
      "text-decoration-thickness": [{
        decoration: ["auto", "from-font", Gt, un]
      }],
      /**
       * Text Underline Offset
       * @see https://tailwindcss.com/docs/text-underline-offset
       */
      "underline-offset": [{
        "underline-offset": ["auto", Gt, de]
      }],
      /**
       * Text Decoration Color
       * @see https://tailwindcss.com/docs/text-decoration-color
       */
      "text-decoration-color": [{
        decoration: [e]
      }],
      /**
       * Text Transform
       * @see https://tailwindcss.com/docs/text-transform
       */
      "text-transform": ["uppercase", "lowercase", "capitalize", "normal-case"],
      /**
       * Text Overflow
       * @see https://tailwindcss.com/docs/text-overflow
       */
      "text-overflow": ["truncate", "text-ellipsis", "text-clip"],
      /**
       * Text Wrap
       * @see https://tailwindcss.com/docs/text-wrap
       */
      "text-wrap": [{
        text: ["wrap", "nowrap", "balance", "pretty"]
      }],
      /**
       * Text Indent
       * @see https://tailwindcss.com/docs/text-indent
       */
      indent: [{
        indent: J()
      }],
      /**
       * Vertical Alignment
       * @see https://tailwindcss.com/docs/vertical-align
       */
      "vertical-align": [{
        align: ["baseline", "top", "middle", "bottom", "text-top", "text-bottom", "sub", "super", de]
      }],
      /**
       * Whitespace
       * @see https://tailwindcss.com/docs/whitespace
       */
      whitespace: [{
        whitespace: ["normal", "nowrap", "pre", "pre-line", "pre-wrap", "break-spaces"]
      }],
      /**
       * Word Break
       * @see https://tailwindcss.com/docs/word-break
       */
      break: [{
        break: ["normal", "words", "all", "keep"]
      }],
      /**
       * Hyphens
       * @see https://tailwindcss.com/docs/hyphens
       */
      hyphens: [{
        hyphens: ["none", "manual", "auto"]
      }],
      /**
       * Content
       * @see https://tailwindcss.com/docs/content
       */
      content: [{
        content: ["none", de]
      }],
      // Backgrounds
      /**
       * Background Attachment
       * @see https://tailwindcss.com/docs/background-attachment
       */
      "bg-attachment": [{
        bg: ["fixed", "local", "scroll"]
      }],
      /**
       * Background Clip
       * @see https://tailwindcss.com/docs/background-clip
       */
      "bg-clip": [{
        "bg-clip": ["border", "padding", "content", "text"]
      }],
      /**
       * Background Opacity
       * @deprecated since Tailwind CSS v3.0.0
       * @see https://tailwindcss.com/docs/background-opacity
       */
      "bg-opacity": [{
        "bg-opacity": [v]
      }],
      /**
       * Background Origin
       * @see https://tailwindcss.com/docs/background-origin
       */
      "bg-origin": [{
        "bg-origin": ["border", "padding", "content"]
      }],
      /**
       * Background Position
       * @see https://tailwindcss.com/docs/background-position
       */
      "bg-position": [{
        bg: [...Pe(), yp]
      }],
      /**
       * Background Repeat
       * @see https://tailwindcss.com/docs/background-repeat
       */
      "bg-repeat": [{
        bg: ["no-repeat", {
          repeat: ["", "x", "y", "round", "space"]
        }]
      }],
      /**
       * Background Size
       * @see https://tailwindcss.com/docs/background-size
       */
      "bg-size": [{
        bg: ["auto", "cover", "contain", vp]
      }],
      /**
       * Background Image
       * @see https://tailwindcss.com/docs/background-image
       */
      "bg-image": [{
        bg: ["none", {
          "gradient-to": ["t", "tr", "r", "br", "b", "bl", "l", "tl"]
        }, wp]
      }],
      /**
       * Background Color
       * @see https://tailwindcss.com/docs/background-color
       */
      "bg-color": [{
        bg: [e]
      }],
      /**
       * Gradient Color Stops From Position
       * @see https://tailwindcss.com/docs/gradient-color-stops
       */
      "gradient-from-pos": [{
        from: [g]
      }],
      /**
       * Gradient Color Stops Via Position
       * @see https://tailwindcss.com/docs/gradient-color-stops
       */
      "gradient-via-pos": [{
        via: [g]
      }],
      /**
       * Gradient Color Stops To Position
       * @see https://tailwindcss.com/docs/gradient-color-stops
       */
      "gradient-to-pos": [{
        to: [g]
      }],
      /**
       * Gradient Color Stops From
       * @see https://tailwindcss.com/docs/gradient-color-stops
       */
      "gradient-from": [{
        from: [h]
      }],
      /**
       * Gradient Color Stops Via
       * @see https://tailwindcss.com/docs/gradient-color-stops
       */
      "gradient-via": [{
        via: [h]
      }],
      /**
       * Gradient Color Stops To
       * @see https://tailwindcss.com/docs/gradient-color-stops
       */
      "gradient-to": [{
        to: [h]
      }],
      // Borders
      /**
       * Border Radius
       * @see https://tailwindcss.com/docs/border-radius
       */
      rounded: [{
        rounded: [s]
      }],
      /**
       * Border Radius Start
       * @see https://tailwindcss.com/docs/border-radius
       */
      "rounded-s": [{
        "rounded-s": [s]
      }],
      /**
       * Border Radius End
       * @see https://tailwindcss.com/docs/border-radius
       */
      "rounded-e": [{
        "rounded-e": [s]
      }],
      /**
       * Border Radius Top
       * @see https://tailwindcss.com/docs/border-radius
       */
      "rounded-t": [{
        "rounded-t": [s]
      }],
      /**
       * Border Radius Right
       * @see https://tailwindcss.com/docs/border-radius
       */
      "rounded-r": [{
        "rounded-r": [s]
      }],
      /**
       * Border Radius Bottom
       * @see https://tailwindcss.com/docs/border-radius
       */
      "rounded-b": [{
        "rounded-b": [s]
      }],
      /**
       * Border Radius Left
       * @see https://tailwindcss.com/docs/border-radius
       */
      "rounded-l": [{
        "rounded-l": [s]
      }],
      /**
       * Border Radius Start Start
       * @see https://tailwindcss.com/docs/border-radius
       */
      "rounded-ss": [{
        "rounded-ss": [s]
      }],
      /**
       * Border Radius Start End
       * @see https://tailwindcss.com/docs/border-radius
       */
      "rounded-se": [{
        "rounded-se": [s]
      }],
      /**
       * Border Radius End End
       * @see https://tailwindcss.com/docs/border-radius
       */
      "rounded-ee": [{
        "rounded-ee": [s]
      }],
      /**
       * Border Radius End Start
       * @see https://tailwindcss.com/docs/border-radius
       */
      "rounded-es": [{
        "rounded-es": [s]
      }],
      /**
       * Border Radius Top Left
       * @see https://tailwindcss.com/docs/border-radius
       */
      "rounded-tl": [{
        "rounded-tl": [s]
      }],
      /**
       * Border Radius Top Right
       * @see https://tailwindcss.com/docs/border-radius
       */
      "rounded-tr": [{
        "rounded-tr": [s]
      }],
      /**
       * Border Radius Bottom Right
       * @see https://tailwindcss.com/docs/border-radius
       */
      "rounded-br": [{
        "rounded-br": [s]
      }],
      /**
       * Border Radius Bottom Left
       * @see https://tailwindcss.com/docs/border-radius
       */
      "rounded-bl": [{
        "rounded-bl": [s]
      }],
      /**
       * Border Width
       * @see https://tailwindcss.com/docs/border-width
       */
      "border-w": [{
        border: [i]
      }],
      /**
       * Border Width X
       * @see https://tailwindcss.com/docs/border-width
       */
      "border-w-x": [{
        "border-x": [i]
      }],
      /**
       * Border Width Y
       * @see https://tailwindcss.com/docs/border-width
       */
      "border-w-y": [{
        "border-y": [i]
      }],
      /**
       * Border Width Start
       * @see https://tailwindcss.com/docs/border-width
       */
      "border-w-s": [{
        "border-s": [i]
      }],
      /**
       * Border Width End
       * @see https://tailwindcss.com/docs/border-width
       */
      "border-w-e": [{
        "border-e": [i]
      }],
      /**
       * Border Width Top
       * @see https://tailwindcss.com/docs/border-width
       */
      "border-w-t": [{
        "border-t": [i]
      }],
      /**
       * Border Width Right
       * @see https://tailwindcss.com/docs/border-width
       */
      "border-w-r": [{
        "border-r": [i]
      }],
      /**
       * Border Width Bottom
       * @see https://tailwindcss.com/docs/border-width
       */
      "border-w-b": [{
        "border-b": [i]
      }],
      /**
       * Border Width Left
       * @see https://tailwindcss.com/docs/border-width
       */
      "border-w-l": [{
        "border-l": [i]
      }],
      /**
       * Border Opacity
       * @see https://tailwindcss.com/docs/border-opacity
       */
      "border-opacity": [{
        "border-opacity": [v]
      }],
      /**
       * Border Style
       * @see https://tailwindcss.com/docs/border-style
       */
      "border-style": [{
        border: [...ue(), "hidden"]
      }],
      /**
       * Divide Width X
       * @see https://tailwindcss.com/docs/divide-width
       */
      "divide-x": [{
        "divide-x": [i]
      }],
      /**
       * Divide Width X Reverse
       * @see https://tailwindcss.com/docs/divide-width
       */
      "divide-x-reverse": ["divide-x-reverse"],
      /**
       * Divide Width Y
       * @see https://tailwindcss.com/docs/divide-width
       */
      "divide-y": [{
        "divide-y": [i]
      }],
      /**
       * Divide Width Y Reverse
       * @see https://tailwindcss.com/docs/divide-width
       */
      "divide-y-reverse": ["divide-y-reverse"],
      /**
       * Divide Opacity
       * @see https://tailwindcss.com/docs/divide-opacity
       */
      "divide-opacity": [{
        "divide-opacity": [v]
      }],
      /**
       * Divide Style
       * @see https://tailwindcss.com/docs/divide-style
       */
      "divide-style": [{
        divide: ue()
      }],
      /**
       * Border Color
       * @see https://tailwindcss.com/docs/border-color
       */
      "border-color": [{
        border: [r]
      }],
      /**
       * Border Color X
       * @see https://tailwindcss.com/docs/border-color
       */
      "border-color-x": [{
        "border-x": [r]
      }],
      /**
       * Border Color Y
       * @see https://tailwindcss.com/docs/border-color
       */
      "border-color-y": [{
        "border-y": [r]
      }],
      /**
       * Border Color S
       * @see https://tailwindcss.com/docs/border-color
       */
      "border-color-s": [{
        "border-s": [r]
      }],
      /**
       * Border Color E
       * @see https://tailwindcss.com/docs/border-color
       */
      "border-color-e": [{
        "border-e": [r]
      }],
      /**
       * Border Color Top
       * @see https://tailwindcss.com/docs/border-color
       */
      "border-color-t": [{
        "border-t": [r]
      }],
      /**
       * Border Color Right
       * @see https://tailwindcss.com/docs/border-color
       */
      "border-color-r": [{
        "border-r": [r]
      }],
      /**
       * Border Color Bottom
       * @see https://tailwindcss.com/docs/border-color
       */
      "border-color-b": [{
        "border-b": [r]
      }],
      /**
       * Border Color Left
       * @see https://tailwindcss.com/docs/border-color
       */
      "border-color-l": [{
        "border-l": [r]
      }],
      /**
       * Divide Color
       * @see https://tailwindcss.com/docs/divide-color
       */
      "divide-color": [{
        divide: [r]
      }],
      /**
       * Outline Style
       * @see https://tailwindcss.com/docs/outline-style
       */
      "outline-style": [{
        outline: ["", ...ue()]
      }],
      /**
       * Outline Offset
       * @see https://tailwindcss.com/docs/outline-offset
       */
      "outline-offset": [{
        "outline-offset": [Gt, de]
      }],
      /**
       * Outline Width
       * @see https://tailwindcss.com/docs/outline-width
       */
      "outline-w": [{
        outline: [Gt, un]
      }],
      /**
       * Outline Color
       * @see https://tailwindcss.com/docs/outline-color
       */
      "outline-color": [{
        outline: [e]
      }],
      /**
       * Ring Width
       * @see https://tailwindcss.com/docs/ring-width
       */
      "ring-w": [{
        ring: ge()
      }],
      /**
       * Ring Width Inset
       * @see https://tailwindcss.com/docs/ring-width
       */
      "ring-w-inset": ["ring-inset"],
      /**
       * Ring Color
       * @see https://tailwindcss.com/docs/ring-color
       */
      "ring-color": [{
        ring: [e]
      }],
      /**
       * Ring Opacity
       * @see https://tailwindcss.com/docs/ring-opacity
       */
      "ring-opacity": [{
        "ring-opacity": [v]
      }],
      /**
       * Ring Offset Width
       * @see https://tailwindcss.com/docs/ring-offset-width
       */
      "ring-offset-w": [{
        "ring-offset": [Gt, un]
      }],
      /**
       * Ring Offset Color
       * @see https://tailwindcss.com/docs/ring-offset-color
       */
      "ring-offset-color": [{
        "ring-offset": [e]
      }],
      // Effects
      /**
       * Box Shadow
       * @see https://tailwindcss.com/docs/box-shadow
       */
      shadow: [{
        shadow: ["", "inner", "none", cn, _p]
      }],
      /**
       * Box Shadow Color
       * @see https://tailwindcss.com/docs/box-shadow-color
       */
      "shadow-color": [{
        shadow: [io]
      }],
      /**
       * Opacity
       * @see https://tailwindcss.com/docs/opacity
       */
      opacity: [{
        opacity: [v]
      }],
      /**
       * Mix Blend Mode
       * @see https://tailwindcss.com/docs/mix-blend-mode
       */
      "mix-blend": [{
        "mix-blend": [...ee(), "plus-lighter", "plus-darker"]
      }],
      /**
       * Background Blend Mode
       * @see https://tailwindcss.com/docs/background-blend-mode
       */
      "bg-blend": [{
        "bg-blend": ee()
      }],
      // Filters
      /**
       * Filter
       * @deprecated since Tailwind CSS v3.0.0
       * @see https://tailwindcss.com/docs/filter
       */
      filter: [{
        filter: ["", "none"]
      }],
      /**
       * Blur
       * @see https://tailwindcss.com/docs/blur
       */
      blur: [{
        blur: [n]
      }],
      /**
       * Brightness
       * @see https://tailwindcss.com/docs/brightness
       */
      brightness: [{
        brightness: [o]
      }],
      /**
       * Contrast
       * @see https://tailwindcss.com/docs/contrast
       */
      contrast: [{
        contrast: [l]
      }],
      /**
       * Drop Shadow
       * @see https://tailwindcss.com/docs/drop-shadow
       */
      "drop-shadow": [{
        "drop-shadow": ["", "none", cn, de]
      }],
      /**
       * Grayscale
       * @see https://tailwindcss.com/docs/grayscale
       */
      grayscale: [{
        grayscale: [u]
      }],
      /**
       * Hue Rotate
       * @see https://tailwindcss.com/docs/hue-rotate
       */
      "hue-rotate": [{
        "hue-rotate": [c]
      }],
      /**
       * Invert
       * @see https://tailwindcss.com/docs/invert
       */
      invert: [{
        invert: [f]
      }],
      /**
       * Saturate
       * @see https://tailwindcss.com/docs/saturate
       */
      saturate: [{
        saturate: [m]
      }],
      /**
       * Sepia
       * @see https://tailwindcss.com/docs/sepia
       */
      sepia: [{
        sepia: [A]
      }],
      /**
       * Backdrop Filter
       * @deprecated since Tailwind CSS v3.0.0
       * @see https://tailwindcss.com/docs/backdrop-filter
       */
      "backdrop-filter": [{
        "backdrop-filter": ["", "none"]
      }],
      /**
       * Backdrop Blur
       * @see https://tailwindcss.com/docs/backdrop-blur
       */
      "backdrop-blur": [{
        "backdrop-blur": [n]
      }],
      /**
       * Backdrop Brightness
       * @see https://tailwindcss.com/docs/backdrop-brightness
       */
      "backdrop-brightness": [{
        "backdrop-brightness": [o]
      }],
      /**
       * Backdrop Contrast
       * @see https://tailwindcss.com/docs/backdrop-contrast
       */
      "backdrop-contrast": [{
        "backdrop-contrast": [l]
      }],
      /**
       * Backdrop Grayscale
       * @see https://tailwindcss.com/docs/backdrop-grayscale
       */
      "backdrop-grayscale": [{
        "backdrop-grayscale": [u]
      }],
      /**
       * Backdrop Hue Rotate
       * @see https://tailwindcss.com/docs/backdrop-hue-rotate
       */
      "backdrop-hue-rotate": [{
        "backdrop-hue-rotate": [c]
      }],
      /**
       * Backdrop Invert
       * @see https://tailwindcss.com/docs/backdrop-invert
       */
      "backdrop-invert": [{
        "backdrop-invert": [f]
      }],
      /**
       * Backdrop Opacity
       * @see https://tailwindcss.com/docs/backdrop-opacity
       */
      "backdrop-opacity": [{
        "backdrop-opacity": [v]
      }],
      /**
       * Backdrop Saturate
       * @see https://tailwindcss.com/docs/backdrop-saturate
       */
      "backdrop-saturate": [{
        "backdrop-saturate": [m]
      }],
      /**
       * Backdrop Sepia
       * @see https://tailwindcss.com/docs/backdrop-sepia
       */
      "backdrop-sepia": [{
        "backdrop-sepia": [A]
      }],
      // Tables
      /**
       * Border Collapse
       * @see https://tailwindcss.com/docs/border-collapse
       */
      "border-collapse": [{
        border: ["collapse", "separate"]
      }],
      /**
       * Border Spacing
       * @see https://tailwindcss.com/docs/border-spacing
       */
      "border-spacing": [{
        "border-spacing": [a]
      }],
      /**
       * Border Spacing X
       * @see https://tailwindcss.com/docs/border-spacing
       */
      "border-spacing-x": [{
        "border-spacing-x": [a]
      }],
      /**
       * Border Spacing Y
       * @see https://tailwindcss.com/docs/border-spacing
       */
      "border-spacing-y": [{
        "border-spacing-y": [a]
      }],
      /**
       * Table Layout
       * @see https://tailwindcss.com/docs/table-layout
       */
      "table-layout": [{
        table: ["auto", "fixed"]
      }],
      /**
       * Caption Side
       * @see https://tailwindcss.com/docs/caption-side
       */
      caption: [{
        caption: ["top", "bottom"]
      }],
      // Transitions and Animation
      /**
       * Tranisition Property
       * @see https://tailwindcss.com/docs/transition-property
       */
      transition: [{
        transition: ["none", "all", "", "colors", "opacity", "shadow", "transform", de]
      }],
      /**
       * Transition Duration
       * @see https://tailwindcss.com/docs/transition-duration
       */
      duration: [{
        duration: ze()
      }],
      /**
       * Transition Timing Function
       * @see https://tailwindcss.com/docs/transition-timing-function
       */
      ease: [{
        ease: ["linear", "in", "out", "in-out", de]
      }],
      /**
       * Transition Delay
       * @see https://tailwindcss.com/docs/transition-delay
       */
      delay: [{
        delay: ze()
      }],
      /**
       * Animation
       * @see https://tailwindcss.com/docs/animation
       */
      animate: [{
        animate: ["none", "spin", "ping", "pulse", "bounce", de]
      }],
      // Transforms
      /**
       * Transform
       * @see https://tailwindcss.com/docs/transform
       */
      transform: [{
        transform: ["", "gpu", "none"]
      }],
      /**
       * Scale
       * @see https://tailwindcss.com/docs/scale
       */
      scale: [{
        scale: [x]
      }],
      /**
       * Scale X
       * @see https://tailwindcss.com/docs/scale
       */
      "scale-x": [{
        "scale-x": [x]
      }],
      /**
       * Scale Y
       * @see https://tailwindcss.com/docs/scale
       */
      "scale-y": [{
        "scale-y": [x]
      }],
      /**
       * Rotate
       * @see https://tailwindcss.com/docs/rotate
       */
      rotate: [{
        rotate: [ao, de]
      }],
      /**
       * Translate X
       * @see https://tailwindcss.com/docs/translate
       */
      "translate-x": [{
        "translate-x": [H]
      }],
      /**
       * Translate Y
       * @see https://tailwindcss.com/docs/translate
       */
      "translate-y": [{
        "translate-y": [H]
      }],
      /**
       * Skew X
       * @see https://tailwindcss.com/docs/skew
       */
      "skew-x": [{
        "skew-x": [R]
      }],
      /**
       * Skew Y
       * @see https://tailwindcss.com/docs/skew
       */
      "skew-y": [{
        "skew-y": [R]
      }],
      /**
       * Transform Origin
       * @see https://tailwindcss.com/docs/transform-origin
       */
      "transform-origin": [{
        origin: ["center", "top", "top-right", "right", "bottom-right", "bottom", "bottom-left", "left", "top-left", de]
      }],
      // Interactivity
      /**
       * Accent Color
       * @see https://tailwindcss.com/docs/accent-color
       */
      accent: [{
        accent: ["auto", e]
      }],
      /**
       * Appearance
       * @see https://tailwindcss.com/docs/appearance
       */
      appearance: [{
        appearance: ["none", "auto"]
      }],
      /**
       * Cursor
       * @see https://tailwindcss.com/docs/cursor
       */
      cursor: [{
        cursor: ["auto", "default", "pointer", "wait", "text", "move", "help", "not-allowed", "none", "context-menu", "progress", "cell", "crosshair", "vertical-text", "alias", "copy", "no-drop", "grab", "grabbing", "all-scroll", "col-resize", "row-resize", "n-resize", "e-resize", "s-resize", "w-resize", "ne-resize", "nw-resize", "se-resize", "sw-resize", "ew-resize", "ns-resize", "nesw-resize", "nwse-resize", "zoom-in", "zoom-out", de]
      }],
      /**
       * Caret Color
       * @see https://tailwindcss.com/docs/just-in-time-mode#caret-color-utilities
       */
      "caret-color": [{
        caret: [e]
      }],
      /**
       * Pointer Events
       * @see https://tailwindcss.com/docs/pointer-events
       */
      "pointer-events": [{
        "pointer-events": ["none", "auto"]
      }],
      /**
       * Resize
       * @see https://tailwindcss.com/docs/resize
       */
      resize: [{
        resize: ["none", "y", "x", ""]
      }],
      /**
       * Scroll Behavior
       * @see https://tailwindcss.com/docs/scroll-behavior
       */
      "scroll-behavior": [{
        scroll: ["auto", "smooth"]
      }],
      /**
       * Scroll Margin
       * @see https://tailwindcss.com/docs/scroll-margin
       */
      "scroll-m": [{
        "scroll-m": J()
      }],
      /**
       * Scroll Margin X
       * @see https://tailwindcss.com/docs/scroll-margin
       */
      "scroll-mx": [{
        "scroll-mx": J()
      }],
      /**
       * Scroll Margin Y
       * @see https://tailwindcss.com/docs/scroll-margin
       */
      "scroll-my": [{
        "scroll-my": J()
      }],
      /**
       * Scroll Margin Start
       * @see https://tailwindcss.com/docs/scroll-margin
       */
      "scroll-ms": [{
        "scroll-ms": J()
      }],
      /**
       * Scroll Margin End
       * @see https://tailwindcss.com/docs/scroll-margin
       */
      "scroll-me": [{
        "scroll-me": J()
      }],
      /**
       * Scroll Margin Top
       * @see https://tailwindcss.com/docs/scroll-margin
       */
      "scroll-mt": [{
        "scroll-mt": J()
      }],
      /**
       * Scroll Margin Right
       * @see https://tailwindcss.com/docs/scroll-margin
       */
      "scroll-mr": [{
        "scroll-mr": J()
      }],
      /**
       * Scroll Margin Bottom
       * @see https://tailwindcss.com/docs/scroll-margin
       */
      "scroll-mb": [{
        "scroll-mb": J()
      }],
      /**
       * Scroll Margin Left
       * @see https://tailwindcss.com/docs/scroll-margin
       */
      "scroll-ml": [{
        "scroll-ml": J()
      }],
      /**
       * Scroll Padding
       * @see https://tailwindcss.com/docs/scroll-padding
       */
      "scroll-p": [{
        "scroll-p": J()
      }],
      /**
       * Scroll Padding X
       * @see https://tailwindcss.com/docs/scroll-padding
       */
      "scroll-px": [{
        "scroll-px": J()
      }],
      /**
       * Scroll Padding Y
       * @see https://tailwindcss.com/docs/scroll-padding
       */
      "scroll-py": [{
        "scroll-py": J()
      }],
      /**
       * Scroll Padding Start
       * @see https://tailwindcss.com/docs/scroll-padding
       */
      "scroll-ps": [{
        "scroll-ps": J()
      }],
      /**
       * Scroll Padding End
       * @see https://tailwindcss.com/docs/scroll-padding
       */
      "scroll-pe": [{
        "scroll-pe": J()
      }],
      /**
       * Scroll Padding Top
       * @see https://tailwindcss.com/docs/scroll-padding
       */
      "scroll-pt": [{
        "scroll-pt": J()
      }],
      /**
       * Scroll Padding Right
       * @see https://tailwindcss.com/docs/scroll-padding
       */
      "scroll-pr": [{
        "scroll-pr": J()
      }],
      /**
       * Scroll Padding Bottom
       * @see https://tailwindcss.com/docs/scroll-padding
       */
      "scroll-pb": [{
        "scroll-pb": J()
      }],
      /**
       * Scroll Padding Left
       * @see https://tailwindcss.com/docs/scroll-padding
       */
      "scroll-pl": [{
        "scroll-pl": J()
      }],
      /**
       * Scroll Snap Align
       * @see https://tailwindcss.com/docs/scroll-snap-align
       */
      "snap-align": [{
        snap: ["start", "end", "center", "align-none"]
      }],
      /**
       * Scroll Snap Stop
       * @see https://tailwindcss.com/docs/scroll-snap-stop
       */
      "snap-stop": [{
        snap: ["normal", "always"]
      }],
      /**
       * Scroll Snap Type
       * @see https://tailwindcss.com/docs/scroll-snap-type
       */
      "snap-type": [{
        snap: ["none", "x", "y", "both"]
      }],
      /**
       * Scroll Snap Type Strictness
       * @see https://tailwindcss.com/docs/scroll-snap-type
       */
      "snap-strictness": [{
        snap: ["mandatory", "proximity"]
      }],
      /**
       * Touch Action
       * @see https://tailwindcss.com/docs/touch-action
       */
      touch: [{
        touch: ["auto", "none", "manipulation"]
      }],
      /**
       * Touch Action X
       * @see https://tailwindcss.com/docs/touch-action
       */
      "touch-x": [{
        "touch-pan": ["x", "left", "right"]
      }],
      /**
       * Touch Action Y
       * @see https://tailwindcss.com/docs/touch-action
       */
      "touch-y": [{
        "touch-pan": ["y", "up", "down"]
      }],
      /**
       * Touch Action Pinch Zoom
       * @see https://tailwindcss.com/docs/touch-action
       */
      "touch-pz": ["touch-pinch-zoom"],
      /**
       * User Select
       * @see https://tailwindcss.com/docs/user-select
       */
      select: [{
        select: ["none", "text", "all", "auto"]
      }],
      /**
       * Will Change
       * @see https://tailwindcss.com/docs/will-change
       */
      "will-change": [{
        "will-change": ["auto", "scroll", "contents", "transform", de]
      }],
      // SVG
      /**
       * Fill
       * @see https://tailwindcss.com/docs/fill
       */
      fill: [{
        fill: [e, "none"]
      }],
      /**
       * Stroke Width
       * @see https://tailwindcss.com/docs/stroke-width
       */
      "stroke-w": [{
        stroke: [Gt, un, Xr]
      }],
      /**
       * Stroke
       * @see https://tailwindcss.com/docs/stroke
       */
      stroke: [{
        stroke: [e, "none"]
      }],
      // Accessibility
      /**
       * Screen Readers
       * @see https://tailwindcss.com/docs/screen-readers
       */
      sr: ["sr-only", "not-sr-only"],
      /**
       * Forced Color Adjust
       * @see https://tailwindcss.com/docs/forced-color-adjust
       */
      "forced-color-adjust": [{
        "forced-color-adjust": ["auto", "none"]
      }]
    },
    conflictingClassGroups: {
      overflow: ["overflow-x", "overflow-y"],
      overscroll: ["overscroll-x", "overscroll-y"],
      inset: ["inset-x", "inset-y", "start", "end", "top", "right", "bottom", "left"],
      "inset-x": ["right", "left"],
      "inset-y": ["top", "bottom"],
      flex: ["basis", "grow", "shrink"],
      gap: ["gap-x", "gap-y"],
      p: ["px", "py", "ps", "pe", "pt", "pr", "pb", "pl"],
      px: ["pr", "pl"],
      py: ["pt", "pb"],
      m: ["mx", "my", "ms", "me", "mt", "mr", "mb", "ml"],
      mx: ["mr", "ml"],
      my: ["mt", "mb"],
      size: ["w", "h"],
      "font-size": ["leading"],
      "fvn-normal": ["fvn-ordinal", "fvn-slashed-zero", "fvn-figure", "fvn-spacing", "fvn-fraction"],
      "fvn-ordinal": ["fvn-normal"],
      "fvn-slashed-zero": ["fvn-normal"],
      "fvn-figure": ["fvn-normal"],
      "fvn-spacing": ["fvn-normal"],
      "fvn-fraction": ["fvn-normal"],
      "line-clamp": ["display", "overflow"],
      rounded: ["rounded-s", "rounded-e", "rounded-t", "rounded-r", "rounded-b", "rounded-l", "rounded-ss", "rounded-se", "rounded-ee", "rounded-es", "rounded-tl", "rounded-tr", "rounded-br", "rounded-bl"],
      "rounded-s": ["rounded-ss", "rounded-es"],
      "rounded-e": ["rounded-se", "rounded-ee"],
      "rounded-t": ["rounded-tl", "rounded-tr"],
      "rounded-r": ["rounded-tr", "rounded-br"],
      "rounded-b": ["rounded-br", "rounded-bl"],
      "rounded-l": ["rounded-tl", "rounded-bl"],
      "border-spacing": ["border-spacing-x", "border-spacing-y"],
      "border-w": ["border-w-s", "border-w-e", "border-w-t", "border-w-r", "border-w-b", "border-w-l"],
      "border-w-x": ["border-w-r", "border-w-l"],
      "border-w-y": ["border-w-t", "border-w-b"],
      "border-color": ["border-color-s", "border-color-e", "border-color-t", "border-color-r", "border-color-b", "border-color-l"],
      "border-color-x": ["border-color-r", "border-color-l"],
      "border-color-y": ["border-color-t", "border-color-b"],
      "scroll-m": ["scroll-mx", "scroll-my", "scroll-ms", "scroll-me", "scroll-mt", "scroll-mr", "scroll-mb", "scroll-ml"],
      "scroll-mx": ["scroll-mr", "scroll-ml"],
      "scroll-my": ["scroll-mt", "scroll-mb"],
      "scroll-p": ["scroll-px", "scroll-py", "scroll-ps", "scroll-pe", "scroll-pt", "scroll-pr", "scroll-pb", "scroll-pl"],
      "scroll-px": ["scroll-pr", "scroll-pl"],
      "scroll-py": ["scroll-pt", "scroll-pb"],
      touch: ["touch-x", "touch-y", "touch-pz"],
      "touch-x": ["touch"],
      "touch-y": ["touch"],
      "touch-pz": ["touch"]
    },
    conflictingClassGroupModifiers: {
      "font-size": ["leading"]
    }
  };
}, Tp = /* @__PURE__ */ ip($p);
function fe(...e) {
  return Tp(eu(e));
}
const Pp = {
  key: 0,
  class: "absolute -top-[2px] -right-[2px] -bottom-[2px] -left-[2px] -z-10 bg-gradient-to-r from-unraid-red to-orange opacity-100 transition-all rounded-md group-hover:opacity-60 group-focus:opacity-60"
}, Ep = {
  key: 1,
  class: "absolute -top-[2px] -right-[2px] -bottom-[2px] -left-[2px] -z-10 bg-gradient-to-r from-unraid-red to-orange opacity-0 transition-all rounded-md group-hover:opacity-100 group-focus:opacity-100"
}, Ap = /* @__PURE__ */ T({
  __name: "BrandButton",
  props: {
    variant: { default: "fill" },
    size: { default: "16px" },
    padding: { default: "default" },
    btnType: { default: "button" },
    class: { default: void 0 },
    click: { type: Function, default: void 0 },
    disabled: { type: Boolean, default: !1 },
    external: { type: Boolean, default: !1 },
    href: { default: void 0 },
    icon: { default: void 0 },
    iconRight: { default: void 0 },
    iconRightHoverDisplay: { type: Boolean, default: !1 },
    text: { default: "" },
    title: { default: "" }
  },
  emits: ["click"],
  setup(e) {
    const t = e, n = O(() => {
      const r = `w-${t.size}`;
      return {
        button: fe(
          Yf({ variant: t.variant, size: t.size, padding: t.padding }),
          t.class
        ),
        icon: `${r} fill-current flex-shrink-0`
      };
    }), o = O(() => ["outline", "outline-primary"].includes(t.variant ?? ""));
    return (r, s) => (_(), E(_t(r.href ? "a" : "button"), {
      disabled: r.disabled,
      href: r.href,
      rel: r.external ? "noopener noreferrer" : "",
      target: r.external ? "_blank" : "",
      type: r.href ? "" : r.btnType,
      class: le(n.value.button),
      title: r.title,
      onClick: s[0] || (s[0] = (a) => r.click ?? r.$emit("click"))
    }, {
      default: S(() => [
        r.variant === "fill" ? (_(), oe("div", Pp)) : Be("", !0),
        o.value ? (_(), oe("div", Ep)) : Be("", !0),
        r.icon ? (_(), E(_t(r.icon), {
          key: 2,
          class: le(n.value.icon)
        }, null, 8, ["class"])) : Be("", !0),
        At(" " + xt(r.text) + " ", 1),
        P(r.$slots, "default"),
        r.iconRight ? (_(), E(_t(r.iconRight), {
          key: 3,
          class: le([
            n.value.icon,
            r.iconRightHoverDisplay && "opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition-all"
          ])
        }, null, 8, ["class"])) : Be("", !0)
      ]),
      _: 3
    }, 8, ["disabled", "href", "rel", "target", "type", "class", "title"]));
  }
}), Op = Bo(
  "inline-flex items-center justify-center w-full h-full aspect-[7/4]",
  {
    variants: {
      variant: {
        default: "",
        black: "text-black fill-black",
        white: "text-white fill-white"
      },
      size: {
        sm: "w-12",
        md: "w-16",
        lg: "w-20",
        full: "w-full",
        custom: ""
      }
    },
    defaultVariants: {
      variant: "default"
    }
  }
), In = {
  mark_2_4: "animate-mark-2",
  mark_3: "animate-mark-3",
  mark_6_8: "animate-mark-6",
  mark_7: "animate-mark-7"
}, Bp = {
  id: "unraidLoadingGradient",
  x1: "23.76",
  y1: "81.49",
  x2: "109.76",
  y2: "-4.51",
  gradientUnits: "userSpaceOnUse"
}, kp = ["stop-color"], Mp = ["stop-color"], Dp = /* @__PURE__ */ T({
  __name: "BrandLoading.ce",
  props: {
    variant: { default: "default", type: null },
    size: { default: "full", type: null },
    class: { type: String },
    title: { default: "Loading", type: String }
  },
  setup(e) {
    const t = e, n = {
      black: { start: "#000000", stop: "#000000" },
      white: { start: "#FFFFFF", stop: "#FFFFFF" },
      default: { start: "#e32929", stop: "#ff8d30" }
    }, o = O(() => n[t.variant]), r = O(() => fe(Op({ variant: t.variant, size: t.size }), t.class));
    return (s, a) => (_(), oe("svg", {
      xmlns: "http://www.w3.org/2000/svg",
      "xmlns:xlink": "http://www.w3.org/1999/xlink",
      viewBox: "0 0 133.52 76.97",
      class: le(r.value),
      role: "img"
    }, [
      Z("title", null, xt(s.title), 1),
      a[0] || (a[0] = Z("desc", null, "Unraid logo animating with a wave like effect", -1)),
      Z("defs", null, [
        Z("linearGradient", Bp, [
          Z("stop", {
            offset: "0",
            "stop-color": o.value.start
          }, null, 8, kp),
          Z("stop", {
            offset: "1",
            "stop-color": o.value.stop
          }, null, 8, Mp)
        ])
      ]),
      a[1] || (a[1] = Z("path", {
        d: "m70,19.24zm57,0l6.54,0l0,38.49l-6.54,0l0,-38.49z",
        fill: "url(#unraidLoadingGradient)",
        class: "unraid_mark_9"
      }, null, -1)),
      Z("path", {
        d: "m70,19.24zm47.65,11.9l-6.55,0l0,-23.79l6.55,0l0,23.79z",
        fill: "url(#unraidLoadingGradient)",
        class: le(["unraid_mark_8", d(In).mark_6_8])
      }, null, 2),
      Z("path", {
        d: "m70,19.24zm31.77,-4.54l-6.54,0l0,-14.7l6.54,0l0,14.7z",
        fill: "url(#unraidLoadingGradient)",
        class: le(["unraid_mark_7", d(In).mark_7])
      }, null, 2),
      Z("path", {
        d: "m70,19.24zm15.9,11.9l-6.54,0l0,-23.79l6.54,0l0,23.79z",
        fill: "url(#unraidLoadingGradient)",
        class: le(["unraid_mark_6", d(In).mark_6_8])
      }, null, 2),
      a[2] || (a[2] = Z("path", {
        d: "m63.49,19.24l6.51,0l0,38.49l-6.51,0l0,-38.49z",
        fill: "url(#unraidLoadingGradient)",
        class: "unraid_mark_5"
      }, null, -1)),
      Z("path", {
        d: "m70,19.24zm-22.38,26.6l6.54,0l0,23.78l-6.54,0l0,-23.78z",
        fill: "url(#unraidLoadingGradient)",
        class: le(["unraid_mark_4", d(In).mark_2_4])
      }, null, 2),
      Z("path", {
        d: "m70,19.24zm-38.26,43.03l6.55,0l0,14.73l-6.55,0l0,-14.73z",
        fill: "url(#unraidLoadingGradient)",
        class: le(["unraid_mark_3", d(In).mark_3])
      }, null, 2),
      Z("path", {
        d: "m70,19.24zm-54.13,26.6l6.54,0l0,23.78l-6.54,0l0,-23.78z",
        fill: "url(#unraidLoadingGradient)",
        class: le(["unraid_mark_2", d(In).mark_2_4])
      }, null, 2),
      a[3] || (a[3] = Z("path", {
        d: "m70,19.24zm-63.46,38.49l-6.54,0l0,-38.49l6.54,0l0,38.49z",
        fill: "url(#unraidLoadingGradient)",
        class: "unraid_mark_1"
      }, null, -1))
    ], 2));
  }
}), Rp = {
  xmlns: "http://www.w3.org/2000/svg",
  "xmlns:xlink": "http://www.w3.org/1999/xlink",
  viewBox: "0 0 222.36 39.04"
}, Ip = {
  id: "unraidLogo",
  x1: "47.53",
  y1: "79.1",
  x2: "170.71",
  y2: "-44.08",
  gradientUnits: "userSpaceOnUse"
}, Lp = ["stop-color"], Fp = ["stop-color"], Vp = /* @__PURE__ */ T({
  __name: "BrandLogo",
  props: {
    gradientStart: { default: "#e32929" },
    gradientStop: { default: "#ff8d30" }
  },
  setup(e) {
    return (t, n) => (_(), oe("svg", Rp, [
      Z("defs", null, [
        Z("linearGradient", Ip, [
          Z("stop", {
            offset: "0",
            "stop-color": t.gradientStart
          }, null, 8, Lp),
          Z("stop", {
            offset: "1",
            "stop-color": t.gradientStop
          }, null, 8, Fp)
        ])
      ]),
      n[0] || (n[0] = Z("title", null, "Unraid Logo", -1)),
      n[1] || (n[1] = Z("path", {
        d: "M146.7,29.47H135l-3,9h-6.49L138.93,0h8l13.41,38.49h-7.09L142.62,6.93l-5.83,16.88h8ZM29.69,0V25.4c0,8.91-5.77,13.64-14.9,13.64S0,34.31,0,25.4V0H6.54V25.4c0,5.17,3.19,7.92,8.25,7.92s8.36-2.75,8.36-7.92V0ZM50.86,12v26.5H44.31V0h6.11l17,26.5V0H74V38.49H67.9ZM171.29,0h6.54V38.49h-6.54Zm51.07,24.69c0,9-5.88,13.8-15.17,13.8H192.67V0H207.3c9.18,0,15.06,4.78,15.06,13.8ZM215.82,13.8c0-5.28-3.3-8.14-8.52-8.14h-8.08V32.77h8c5.33,0,8.63-2.8,8.63-8.08ZM108.31,23.92c4.34-1.6,6.93-5.28,6.93-11.55C115.24,3.68,110.18,0,102.48,0H88.84V38.49h6.55V5.66h6.87c3.8,0,6.21,1.82,6.21,6.71s-2.41,6.76-6.21,6.76H98.88l9.21,19.36h7.53Z",
        fill: "url(#unraidLogo)"
      }, null, -1))
    ]));
  }
}), Np = {
  xmlns: "http://www.w3.org/2000/svg",
  "xmlns:xlink": "http://www.w3.org/1999/xlink",
  "data-name": "Layer 1",
  viewBox: "0 0 954.29 142.4"
}, zp = {
  id: "a",
  x1: "-57.82",
  x2: "923.39",
  y1: "71.2",
  y2: "71.2",
  gradientUnits: "userSpaceOnUse"
}, jp = ["stop-color"], Hp = ["stop-color"], Wp = /* @__PURE__ */ T({
  __name: "BrandLogoConnect",
  props: {
    gradientStart: { default: "#e32929" },
    gradientStop: { default: "#ff8d30" }
  },
  setup(e) {
    return (t, n) => (_(), oe("svg", Np, [
      Z("defs", null, [
        Z("linearGradient", zp, [
          Z("stop", {
            offset: "0",
            "stop-color": t.gradientStart
          }, null, 8, jp),
          Z("stop", {
            offset: "1",
            "stop-color": t.gradientStop
          }, null, 8, Hp)
        ]),
        n[0] || (n[0] = Wa('<linearGradient id="b" xlink:href="#a" x2="923.39"></linearGradient><linearGradient id="c" xlink:href="#a" x2="923.39" y1="71.2" y2="71.2"></linearGradient><linearGradient id="d" xlink:href="#a" x2="923.39" y1="71.2" y2="71.2"></linearGradient><linearGradient id="e" xlink:href="#a" x2="923.39" y1="71.2" y2="71.2"></linearGradient><linearGradient id="f" xlink:href="#a" x2="923.39"></linearGradient><linearGradient id="g" xlink:href="#a" y1="12.16" y2="12.16"></linearGradient><linearGradient id="h" xlink:href="#a" x2="923.39" y1="86.94" y2="86.94"></linearGradient>', 7))
      ]),
      n[1] || (n[1] = Wa('<path fill="url(#a)" d="M54.39 0C20.96 0 0 17.4 0 49.84v42.52c0 32.63 20.96 50.04 53.99 50.04s53.8-16.81 53.8-48.06v-.99H84.25v.99c0 17.8-11.47 27.49-30.26 27.49s-30.46-10.28-30.46-29.47V49.84c0-18.99 11.67-29.47 30.85-29.47s29.86 9.89 29.86 27.69v.79h23.54v-.79C107.79 16.81 87.02 0 54.39 0Z"></path><path fill="url(#b)" d="M197.58 0c-33.42 0-54.59 17.4-54.59 49.84v42.52c0 32.63 21.16 50.04 54.19 50.04s54.59-17.4 54.59-50.04V49.84C251.77 17.4 230.61 0 197.58 0Zm30.66 92.36c0 19.18-11.87 29.47-31.05 29.47s-30.66-10.28-30.66-29.47V49.84c0-18.99 11.87-29.47 31.05-29.47s30.66 10.48 30.66 29.47v42.52Z"></path><path fill="url(#c)" d="M373.8 97.31 312.49 1.98h-21.95v138.44h23.53V45.09l61.32 95.33h21.95V1.98H373.8v95.33z"></path><path fill="url(#d)" d="M521.35 97.31 460.04 1.98h-21.96v138.44h23.54V45.09l61.31 95.33h21.95V1.98h-23.53v95.33z"></path><path fill="url(#e)" d="M585.63 140.42h92.95v-20.57h-69.42V81.29h59.54V60.92h-59.54V22.35h69.42V1.98h-92.95v138.44z"></path><path fill="url(#f)" d="M766.8 0c-33.43 0-54.39 17.4-54.39 49.84v42.52c0 32.63 20.96 50.04 53.99 50.04s53.8-16.81 53.8-48.06v-.99h-23.54v.99c0 17.8-11.47 27.49-30.26 27.49s-30.46-10.28-30.46-29.47V49.84c0-18.99 11.67-29.47 30.85-29.47s29.86 9.89 29.86 27.69v.79h23.54v-.79c0-31.25-20.77-48.06-53.4-48.06Z"></path><path fill="url(#g)" d="M846.11 1.98h108.18v20.37H846.11z"></path><path fill="url(#h)" d="M888.43 33.45h23.54v106.97h-23.54z"></path>', 8))
    ]));
  }
}), Kp = Bo(
  "inline-flex items-center justify-center rounded-md text-base font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50",
  {
    variants: {
      variant: {
        primary: "bg-primary text-primary-foreground hover:bg-primary/90",
        destructive: "bg-destructive text-destructive-foreground hover:bg-destructive/90",
        outline: "border border-input bg-background hover:bg-accent hover:text-accent-foreground",
        secondary: "bg-secondary text-secondary-foreground hover:bg-secondary/80",
        ghost: "hover:bg-accent hover:text-accent-foreground",
        link: "text-primary underline-offset-4 hover:underline"
      },
      size: {
        sm: "h-9 rounded-md px-3",
        md: "h-10 px-4 py-2",
        lg: "h-11 rounded-md px-8",
        icon: "h-10 w-10"
      }
    },
    defaultVariants: {
      variant: "primary",
      size: "md"
    }
  }
), au = /* @__PURE__ */ T({
  __name: "Button",
  props: {
    variant: { default: "primary" },
    size: { default: "md" },
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => fe(Kp({ variant: t.variant, size: t.size }), t.class));
    return (o, r) => (_(), oe("button", {
      class: le(n.value)
    }, [
      P(o.$slots, "default")
    ], 2));
  }
}), Up = /* @__PURE__ */ T({
  __name: "CardWrapper",
  props: {
    error: { type: Boolean, default: !1 },
    hover: { type: Boolean, default: !0 },
    increasedPadding: { type: Boolean, default: !1 },
    padding: { type: Boolean, default: !0 },
    warning: { type: Boolean, default: !1 }
  },
  setup(e) {
    return (t, n) => (_(), oe("div", {
      class: le(["group/card text-left relative flex flex-col flex-1 border-2 border-solid rounded-md shadow-md", [
        t.padding && "p-4",
        t.increasedPadding && "md:p-6",
        t.hover && "hover:shadow-orange/50 transition-all",
        t.error && "text-white bg-unraid-red border-unraid-red",
        t.warning && "text-black bg-yellow-100 border-yellow-100",
        !t.error && !t.warning && "text-foreground bg-background border-muted"
      ]])
    }, [
      P(t.$slots, "default")
    ], 2));
  }
}), Gp = /* @__PURE__ */ T({
  __name: "PageContainer",
  props: {
    maxWidth: { default: "max-w-1024px" }
  },
  setup(e) {
    return (t, n) => (_(), oe("div", {
      class: le(["grid gap-y-24px w-full mx-auto px-16px", t.maxWidth])
    }, [
      P(t.$slots, "default")
    ], 2));
  }
}), Yp = ["top", "right", "bottom", "left"], gn = Math.min, ft = Math.max, sr = Math.round, Vo = Math.floor, Lt = (e) => ({
  x: e,
  y: e
}), qp = {
  left: "right",
  right: "left",
  bottom: "top",
  top: "bottom"
}, Xp = {
  start: "end",
  end: "start"
};
function ws(e, t, n) {
  return ft(e, gn(t, n));
}
function Qt(e, t) {
  return typeof e == "function" ? e(t) : e;
}
function en(e) {
  return e.split("-")[0];
}
function Qn(e) {
  return e.split("-")[1];
}
function Zs(e) {
  return e === "x" ? "y" : "x";
}
function Qs(e) {
  return e === "y" ? "height" : "width";
}
function vn(e) {
  return ["top", "bottom"].includes(en(e)) ? "y" : "x";
}
function ea(e) {
  return Zs(vn(e));
}
function Jp(e, t, n) {
  n === void 0 && (n = !1);
  const o = Qn(e), r = ea(e), s = Qs(r);
  let a = r === "x" ? o === (n ? "end" : "start") ? "right" : "left" : o === "start" ? "bottom" : "top";
  return t.reference[s] > t.floating[s] && (a = ar(a)), [a, ar(a)];
}
function Zp(e) {
  const t = ar(e);
  return [_s(e), t, _s(t)];
}
function _s(e) {
  return e.replace(/start|end/g, (t) => Xp[t]);
}
function Qp(e, t, n) {
  const o = ["left", "right"], r = ["right", "left"], s = ["top", "bottom"], a = ["bottom", "top"];
  switch (e) {
    case "top":
    case "bottom":
      return n ? t ? r : o : t ? o : r;
    case "left":
    case "right":
      return t ? s : a;
    default:
      return [];
  }
}
function eh(e, t, n, o) {
  const r = Qn(e);
  let s = Qp(en(e), n === "start", o);
  return r && (s = s.map((a) => a + "-" + r), t && (s = s.concat(s.map(_s)))), s;
}
function ar(e) {
  return e.replace(/left|right|bottom|top/g, (t) => qp[t]);
}
function th(e) {
  return {
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
    ...e
  };
}
function iu(e) {
  return typeof e != "number" ? th(e) : {
    top: e,
    right: e,
    bottom: e,
    left: e
  };
}
function ir(e) {
  const {
    x: t,
    y: n,
    width: o,
    height: r
  } = e;
  return {
    width: o,
    height: r,
    top: n,
    left: t,
    right: t + o,
    bottom: n + r,
    x: t,
    y: n
  };
}
function pi(e, t, n) {
  let {
    reference: o,
    floating: r
  } = e;
  const s = vn(t), a = ea(t), i = Qs(a), l = en(t), u = s === "y", c = o.x + o.width / 2 - r.width / 2, f = o.y + o.height / 2 - r.height / 2, p = o[i] / 2 - r[i] / 2;
  let h;
  switch (l) {
    case "top":
      h = {
        x: c,
        y: o.y - r.height
      };
      break;
    case "bottom":
      h = {
        x: c,
        y: o.y + o.height
      };
      break;
    case "right":
      h = {
        x: o.x + o.width,
        y: f
      };
      break;
    case "left":
      h = {
        x: o.x - r.width,
        y: f
      };
      break;
    default:
      h = {
        x: o.x,
        y: o.y
      };
  }
  switch (Qn(t)) {
    case "start":
      h[a] -= p * (n && u ? -1 : 1);
      break;
    case "end":
      h[a] += p * (n && u ? -1 : 1);
      break;
  }
  return h;
}
const nh = async (e, t, n) => {
  const {
    placement: o = "bottom",
    strategy: r = "absolute",
    middleware: s = [],
    platform: a
  } = n, i = s.filter(Boolean), l = await (a.isRTL == null ? void 0 : a.isRTL(t));
  let u = await a.getElementRects({
    reference: e,
    floating: t,
    strategy: r
  }), {
    x: c,
    y: f
  } = pi(u, o, l), p = o, h = {}, g = 0;
  for (let y = 0; y < i.length; y++) {
    const {
      name: C,
      fn: v
    } = i[y], {
      x: w,
      y: m,
      data: x,
      reset: A
    } = await v({
      x: c,
      y: f,
      initialPlacement: o,
      placement: p,
      strategy: r,
      middlewareData: h,
      rects: u,
      platform: a,
      elements: {
        reference: e,
        floating: t
      }
    });
    c = w ?? c, f = m ?? f, h = {
      ...h,
      [C]: {
        ...h[C],
        ...x
      }
    }, A && g <= 50 && (g++, typeof A == "object" && (A.placement && (p = A.placement), A.rects && (u = A.rects === !0 ? await a.getElementRects({
      reference: e,
      floating: t,
      strategy: r
    }) : A.rects), {
      x: c,
      y: f
    } = pi(u, p, l)), y = -1);
  }
  return {
    x: c,
    y: f,
    placement: p,
    strategy: r,
    middlewareData: h
  };
};
async function So(e, t) {
  var n;
  t === void 0 && (t = {});
  const {
    x: o,
    y: r,
    platform: s,
    rects: a,
    elements: i,
    strategy: l
  } = e, {
    boundary: u = "clippingAncestors",
    rootBoundary: c = "viewport",
    elementContext: f = "floating",
    altBoundary: p = !1,
    padding: h = 0
  } = Qt(t, e), g = iu(h), C = i[p ? f === "floating" ? "reference" : "floating" : f], v = ir(await s.getClippingRect({
    element: (n = await (s.isElement == null ? void 0 : s.isElement(C))) == null || n ? C : C.contextElement || await (s.getDocumentElement == null ? void 0 : s.getDocumentElement(i.floating)),
    boundary: u,
    rootBoundary: c,
    strategy: l
  })), w = f === "floating" ? {
    x: o,
    y: r,
    width: a.floating.width,
    height: a.floating.height
  } : a.reference, m = await (s.getOffsetParent == null ? void 0 : s.getOffsetParent(i.floating)), x = await (s.isElement == null ? void 0 : s.isElement(m)) ? await (s.getScale == null ? void 0 : s.getScale(m)) || {
    x: 1,
    y: 1
  } : {
    x: 1,
    y: 1
  }, A = ir(s.convertOffsetParentRelativeRectToViewportRelativeRect ? await s.convertOffsetParentRelativeRectToViewportRelativeRect({
    elements: i,
    rect: w,
    offsetParent: m,
    strategy: l
  }) : w);
  return {
    top: (v.top - A.top + g.top) / x.y,
    bottom: (A.bottom - v.bottom + g.bottom) / x.y,
    left: (v.left - A.left + g.left) / x.x,
    right: (A.right - v.right + g.right) / x.x
  };
}
const oh = (e) => ({
  name: "arrow",
  options: e,
  async fn(t) {
    const {
      x: n,
      y: o,
      placement: r,
      rects: s,
      platform: a,
      elements: i,
      middlewareData: l
    } = t, {
      element: u,
      padding: c = 0
    } = Qt(e, t) || {};
    if (u == null)
      return {};
    const f = iu(c), p = {
      x: n,
      y: o
    }, h = ea(r), g = Qs(h), y = await a.getDimensions(u), C = h === "y", v = C ? "top" : "left", w = C ? "bottom" : "right", m = C ? "clientHeight" : "clientWidth", x = s.reference[g] + s.reference[h] - p[h] - s.floating[g], A = p[h] - s.reference[h], R = await (a.getOffsetParent == null ? void 0 : a.getOffsetParent(u));
    let I = R ? R[m] : 0;
    (!I || !await (a.isElement == null ? void 0 : a.isElement(R))) && (I = i.floating[m] || s.floating[g]);
    const H = x / 2 - A / 2, k = I / 2 - y[g] / 2 - 1, j = gn(f[v], k), K = gn(f[w], k), J = j, ge = I - y[g] - K, ce = I / 2 - y[g] / 2 + H, Pe = ws(J, ce, ge), ue = !l.arrow && Qn(r) != null && ce !== Pe && s.reference[g] / 2 - (ce < J ? j : K) - y[g] / 2 < 0, ee = ue ? ce < J ? ce - J : ce - ge : 0;
    return {
      [h]: p[h] + ee,
      data: {
        [h]: Pe,
        centerOffset: ce - Pe - ee,
        ...ue && {
          alignmentOffset: ee
        }
      },
      reset: ue
    };
  }
}), rh = function(e) {
  return e === void 0 && (e = {}), {
    name: "flip",
    options: e,
    async fn(t) {
      var n, o;
      const {
        placement: r,
        middlewareData: s,
        rects: a,
        initialPlacement: i,
        platform: l,
        elements: u
      } = t, {
        mainAxis: c = !0,
        crossAxis: f = !0,
        fallbackPlacements: p,
        fallbackStrategy: h = "bestFit",
        fallbackAxisSideDirection: g = "none",
        flipAlignment: y = !0,
        ...C
      } = Qt(e, t);
      if ((n = s.arrow) != null && n.alignmentOffset)
        return {};
      const v = en(r), w = vn(i), m = en(i) === i, x = await (l.isRTL == null ? void 0 : l.isRTL(u.floating)), A = p || (m || !y ? [ar(i)] : Zp(i)), R = g !== "none";
      !p && R && A.push(...eh(i, y, g, x));
      const I = [i, ...A], H = await So(t, C), k = [];
      let j = ((o = s.flip) == null ? void 0 : o.overflows) || [];
      if (c && k.push(H[v]), f) {
        const ce = Jp(r, a, x);
        k.push(H[ce[0]], H[ce[1]]);
      }
      if (j = [...j, {
        placement: r,
        overflows: k
      }], !k.every((ce) => ce <= 0)) {
        var K, J;
        const ce = (((K = s.flip) == null ? void 0 : K.index) || 0) + 1, Pe = I[ce];
        if (Pe)
          return {
            data: {
              index: ce,
              overflows: j
            },
            reset: {
              placement: Pe
            }
          };
        let ue = (J = j.filter((ee) => ee.overflows[0] <= 0).sort((ee, ne) => ee.overflows[1] - ne.overflows[1])[0]) == null ? void 0 : J.placement;
        if (!ue)
          switch (h) {
            case "bestFit": {
              var ge;
              const ee = (ge = j.filter((ne) => {
                if (R) {
                  const he = vn(ne.placement);
                  return he === w || // Create a bias to the `y` side axis due to horizontal
                  // reading directions favoring greater width.
                  he === "y";
                }
                return !0;
              }).map((ne) => [ne.placement, ne.overflows.filter((he) => he > 0).reduce((he, je) => he + je, 0)]).sort((ne, he) => ne[1] - he[1])[0]) == null ? void 0 : ge[0];
              ee && (ue = ee);
              break;
            }
            case "initialPlacement":
              ue = i;
              break;
          }
        if (r !== ue)
          return {
            reset: {
              placement: ue
            }
          };
      }
      return {};
    }
  };
};
function hi(e, t) {
  return {
    top: e.top - t.height,
    right: e.right - t.width,
    bottom: e.bottom - t.height,
    left: e.left - t.width
  };
}
function mi(e) {
  return Yp.some((t) => e[t] >= 0);
}
const sh = function(e) {
  return e === void 0 && (e = {}), {
    name: "hide",
    options: e,
    async fn(t) {
      const {
        rects: n
      } = t, {
        strategy: o = "referenceHidden",
        ...r
      } = Qt(e, t);
      switch (o) {
        case "referenceHidden": {
          const s = await So(t, {
            ...r,
            elementContext: "reference"
          }), a = hi(s, n.reference);
          return {
            data: {
              referenceHiddenOffsets: a,
              referenceHidden: mi(a)
            }
          };
        }
        case "escaped": {
          const s = await So(t, {
            ...r,
            altBoundary: !0
          }), a = hi(s, n.floating);
          return {
            data: {
              escapedOffsets: a,
              escaped: mi(a)
            }
          };
        }
        default:
          return {};
      }
    }
  };
};
async function ah(e, t) {
  const {
    placement: n,
    platform: o,
    elements: r
  } = e, s = await (o.isRTL == null ? void 0 : o.isRTL(r.floating)), a = en(n), i = Qn(n), l = vn(n) === "y", u = ["left", "top"].includes(a) ? -1 : 1, c = s && l ? -1 : 1, f = Qt(t, e);
  let {
    mainAxis: p,
    crossAxis: h,
    alignmentAxis: g
  } = typeof f == "number" ? {
    mainAxis: f,
    crossAxis: 0,
    alignmentAxis: null
  } : {
    mainAxis: f.mainAxis || 0,
    crossAxis: f.crossAxis || 0,
    alignmentAxis: f.alignmentAxis
  };
  return i && typeof g == "number" && (h = i === "end" ? g * -1 : g), l ? {
    x: h * c,
    y: p * u
  } : {
    x: p * u,
    y: h * c
  };
}
const ih = function(e) {
  return e === void 0 && (e = 0), {
    name: "offset",
    options: e,
    async fn(t) {
      var n, o;
      const {
        x: r,
        y: s,
        placement: a,
        middlewareData: i
      } = t, l = await ah(t, e);
      return a === ((n = i.offset) == null ? void 0 : n.placement) && (o = i.arrow) != null && o.alignmentOffset ? {} : {
        x: r + l.x,
        y: s + l.y,
        data: {
          ...l,
          placement: a
        }
      };
    }
  };
}, lh = function(e) {
  return e === void 0 && (e = {}), {
    name: "shift",
    options: e,
    async fn(t) {
      const {
        x: n,
        y: o,
        placement: r
      } = t, {
        mainAxis: s = !0,
        crossAxis: a = !1,
        limiter: i = {
          fn: (C) => {
            let {
              x: v,
              y: w
            } = C;
            return {
              x: v,
              y: w
            };
          }
        },
        ...l
      } = Qt(e, t), u = {
        x: n,
        y: o
      }, c = await So(t, l), f = vn(en(r)), p = Zs(f);
      let h = u[p], g = u[f];
      if (s) {
        const C = p === "y" ? "top" : "left", v = p === "y" ? "bottom" : "right", w = h + c[C], m = h - c[v];
        h = ws(w, h, m);
      }
      if (a) {
        const C = f === "y" ? "top" : "left", v = f === "y" ? "bottom" : "right", w = g + c[C], m = g - c[v];
        g = ws(w, g, m);
      }
      const y = i.fn({
        ...t,
        [p]: h,
        [f]: g
      });
      return {
        ...y,
        data: {
          x: y.x - n,
          y: y.y - o,
          enabled: {
            [p]: s,
            [f]: a
          }
        }
      };
    }
  };
}, uh = function(e) {
  return e === void 0 && (e = {}), {
    options: e,
    fn(t) {
      const {
        x: n,
        y: o,
        placement: r,
        rects: s,
        middlewareData: a
      } = t, {
        offset: i = 0,
        mainAxis: l = !0,
        crossAxis: u = !0
      } = Qt(e, t), c = {
        x: n,
        y: o
      }, f = vn(r), p = Zs(f);
      let h = c[p], g = c[f];
      const y = Qt(i, t), C = typeof y == "number" ? {
        mainAxis: y,
        crossAxis: 0
      } : {
        mainAxis: 0,
        crossAxis: 0,
        ...y
      };
      if (l) {
        const m = p === "y" ? "height" : "width", x = s.reference[p] - s.floating[m] + C.mainAxis, A = s.reference[p] + s.reference[m] - C.mainAxis;
        h < x ? h = x : h > A && (h = A);
      }
      if (u) {
        var v, w;
        const m = p === "y" ? "width" : "height", x = ["top", "left"].includes(en(r)), A = s.reference[f] - s.floating[m] + (x && ((v = a.offset) == null ? void 0 : v[f]) || 0) + (x ? 0 : C.crossAxis), R = s.reference[f] + s.reference[m] + (x ? 0 : ((w = a.offset) == null ? void 0 : w[f]) || 0) - (x ? C.crossAxis : 0);
        g < A ? g = A : g > R && (g = R);
      }
      return {
        [p]: h,
        [f]: g
      };
    }
  };
}, ch = function(e) {
  return e === void 0 && (e = {}), {
    name: "size",
    options: e,
    async fn(t) {
      var n, o;
      const {
        placement: r,
        rects: s,
        platform: a,
        elements: i
      } = t, {
        apply: l = () => {
        },
        ...u
      } = Qt(e, t), c = await So(t, u), f = en(r), p = Qn(r), h = vn(r) === "y", {
        width: g,
        height: y
      } = s.floating;
      let C, v;
      f === "top" || f === "bottom" ? (C = f, v = p === (await (a.isRTL == null ? void 0 : a.isRTL(i.floating)) ? "start" : "end") ? "left" : "right") : (v = f, C = p === "end" ? "top" : "bottom");
      const w = y - c.top - c.bottom, m = g - c.left - c.right, x = gn(y - c[C], w), A = gn(g - c[v], m), R = !t.middlewareData.shift;
      let I = x, H = A;
      if ((n = t.middlewareData.shift) != null && n.enabled.x && (H = m), (o = t.middlewareData.shift) != null && o.enabled.y && (I = w), R && !p) {
        const j = ft(c.left, 0), K = ft(c.right, 0), J = ft(c.top, 0), ge = ft(c.bottom, 0);
        h ? H = g - 2 * (j !== 0 || K !== 0 ? j + K : ft(c.left, c.right)) : I = y - 2 * (J !== 0 || ge !== 0 ? J + ge : ft(c.top, c.bottom));
      }
      await l({
        ...t,
        availableWidth: H,
        availableHeight: I
      });
      const k = await a.getDimensions(i.floating);
      return g !== k.width || y !== k.height ? {
        reset: {
          rects: !0
        }
      } : {};
    }
  };
};
function Pr() {
  return typeof window < "u";
}
function En(e) {
  return ta(e) ? (e.nodeName || "").toLowerCase() : "#document";
}
function gt(e) {
  var t;
  return (e == null || (t = e.ownerDocument) == null ? void 0 : t.defaultView) || window;
}
function Ht(e) {
  var t;
  return (t = (ta(e) ? e.ownerDocument : e.document) || window.document) == null ? void 0 : t.documentElement;
}
function ta(e) {
  return Pr() ? e instanceof Node || e instanceof gt(e).Node : !1;
}
function Ot(e) {
  return Pr() ? e instanceof Element || e instanceof gt(e).Element : !1;
}
function Nt(e) {
  return Pr() ? e instanceof HTMLElement || e instanceof gt(e).HTMLElement : !1;
}
function gi(e) {
  return !Pr() || typeof ShadowRoot > "u" ? !1 : e instanceof ShadowRoot || e instanceof gt(e).ShadowRoot;
}
function ko(e) {
  const {
    overflow: t,
    overflowX: n,
    overflowY: o,
    display: r
  } = Bt(e);
  return /auto|scroll|overlay|hidden|clip/.test(t + o + n) && !["inline", "contents"].includes(r);
}
function dh(e) {
  return ["table", "td", "th"].includes(En(e));
}
function Er(e) {
  return [":popover-open", ":modal"].some((t) => {
    try {
      return e.matches(t);
    } catch {
      return !1;
    }
  });
}
function na(e) {
  const t = oa(), n = Ot(e) ? Bt(e) : e;
  return ["transform", "translate", "scale", "rotate", "perspective"].some((o) => n[o] ? n[o] !== "none" : !1) || (n.containerType ? n.containerType !== "normal" : !1) || !t && (n.backdropFilter ? n.backdropFilter !== "none" : !1) || !t && (n.filter ? n.filter !== "none" : !1) || ["transform", "translate", "scale", "rotate", "perspective", "filter"].some((o) => (n.willChange || "").includes(o)) || ["paint", "layout", "strict", "content"].some((o) => (n.contain || "").includes(o));
}
function fh(e) {
  let t = yn(e);
  for (; Nt(t) && !qn(t); ) {
    if (na(t))
      return t;
    if (Er(t))
      return null;
    t = yn(t);
  }
  return null;
}
function oa() {
  return typeof CSS > "u" || !CSS.supports ? !1 : CSS.supports("-webkit-backdrop-filter", "none");
}
function qn(e) {
  return ["html", "body", "#document"].includes(En(e));
}
function Bt(e) {
  return gt(e).getComputedStyle(e);
}
function Ar(e) {
  return Ot(e) ? {
    scrollLeft: e.scrollLeft,
    scrollTop: e.scrollTop
  } : {
    scrollLeft: e.scrollX,
    scrollTop: e.scrollY
  };
}
function yn(e) {
  if (En(e) === "html")
    return e;
  const t = (
    // Step into the shadow DOM of the parent of a slotted node.
    e.assignedSlot || // DOM Element detected.
    e.parentNode || // ShadowRoot detected.
    gi(e) && e.host || // Fallback.
    Ht(e)
  );
  return gi(t) ? t.host : t;
}
function lu(e) {
  const t = yn(e);
  return qn(t) ? e.ownerDocument ? e.ownerDocument.body : e.body : Nt(t) && ko(t) ? t : lu(t);
}
function $o(e, t, n) {
  var o;
  t === void 0 && (t = []), n === void 0 && (n = !0);
  const r = lu(e), s = r === ((o = e.ownerDocument) == null ? void 0 : o.body), a = gt(r);
  if (s) {
    const i = xs(a);
    return t.concat(a, a.visualViewport || [], ko(r) ? r : [], i && n ? $o(i) : []);
  }
  return t.concat(r, $o(r, [], n));
}
function xs(e) {
  return e.parent && Object.getPrototypeOf(e.parent) ? e.frameElement : null;
}
function uu(e) {
  const t = Bt(e);
  let n = parseFloat(t.width) || 0, o = parseFloat(t.height) || 0;
  const r = Nt(e), s = r ? e.offsetWidth : n, a = r ? e.offsetHeight : o, i = sr(n) !== s || sr(o) !== a;
  return i && (n = s, o = a), {
    width: n,
    height: o,
    $: i
  };
}
function ra(e) {
  return Ot(e) ? e : e.contextElement;
}
function Yn(e) {
  const t = ra(e);
  if (!Nt(t))
    return Lt(1);
  const n = t.getBoundingClientRect(), {
    width: o,
    height: r,
    $: s
  } = uu(t);
  let a = (s ? sr(n.width) : n.width) / o, i = (s ? sr(n.height) : n.height) / r;
  return (!a || !Number.isFinite(a)) && (a = 1), (!i || !Number.isFinite(i)) && (i = 1), {
    x: a,
    y: i
  };
}
const ph = /* @__PURE__ */ Lt(0);
function cu(e) {
  const t = gt(e);
  return !oa() || !t.visualViewport ? ph : {
    x: t.visualViewport.offsetLeft,
    y: t.visualViewport.offsetTop
  };
}
function hh(e, t, n) {
  return t === void 0 && (t = !1), !n || t && n !== gt(e) ? !1 : t;
}
function Tn(e, t, n, o) {
  t === void 0 && (t = !1), n === void 0 && (n = !1);
  const r = e.getBoundingClientRect(), s = ra(e);
  let a = Lt(1);
  t && (o ? Ot(o) && (a = Yn(o)) : a = Yn(e));
  const i = hh(s, n, o) ? cu(s) : Lt(0);
  let l = (r.left + i.x) / a.x, u = (r.top + i.y) / a.y, c = r.width / a.x, f = r.height / a.y;
  if (s) {
    const p = gt(s), h = o && Ot(o) ? gt(o) : o;
    let g = p, y = xs(g);
    for (; y && o && h !== g; ) {
      const C = Yn(y), v = y.getBoundingClientRect(), w = Bt(y), m = v.left + (y.clientLeft + parseFloat(w.paddingLeft)) * C.x, x = v.top + (y.clientTop + parseFloat(w.paddingTop)) * C.y;
      l *= C.x, u *= C.y, c *= C.x, f *= C.y, l += m, u += x, g = gt(y), y = xs(g);
    }
  }
  return ir({
    width: c,
    height: f,
    x: l,
    y: u
  });
}
function sa(e, t) {
  const n = Ar(e).scrollLeft;
  return t ? t.left + n : Tn(Ht(e)).left + n;
}
function du(e, t, n) {
  n === void 0 && (n = !1);
  const o = e.getBoundingClientRect(), r = o.left + t.scrollLeft - (n ? 0 : (
    // RTL <body> scrollbar.
    sa(e, o)
  )), s = o.top + t.scrollTop;
  return {
    x: r,
    y: s
  };
}
function mh(e) {
  let {
    elements: t,
    rect: n,
    offsetParent: o,
    strategy: r
  } = e;
  const s = r === "fixed", a = Ht(o), i = t ? Er(t.floating) : !1;
  if (o === a || i && s)
    return n;
  let l = {
    scrollLeft: 0,
    scrollTop: 0
  }, u = Lt(1);
  const c = Lt(0), f = Nt(o);
  if ((f || !f && !s) && ((En(o) !== "body" || ko(a)) && (l = Ar(o)), Nt(o))) {
    const h = Tn(o);
    u = Yn(o), c.x = h.x + o.clientLeft, c.y = h.y + o.clientTop;
  }
  const p = a && !f && !s ? du(a, l, !0) : Lt(0);
  return {
    width: n.width * u.x,
    height: n.height * u.y,
    x: n.x * u.x - l.scrollLeft * u.x + c.x + p.x,
    y: n.y * u.y - l.scrollTop * u.y + c.y + p.y
  };
}
function gh(e) {
  return Array.from(e.getClientRects());
}
function vh(e) {
  const t = Ht(e), n = Ar(e), o = e.ownerDocument.body, r = ft(t.scrollWidth, t.clientWidth, o.scrollWidth, o.clientWidth), s = ft(t.scrollHeight, t.clientHeight, o.scrollHeight, o.clientHeight);
  let a = -n.scrollLeft + sa(e);
  const i = -n.scrollTop;
  return Bt(o).direction === "rtl" && (a += ft(t.clientWidth, o.clientWidth) - r), {
    width: r,
    height: s,
    x: a,
    y: i
  };
}
function yh(e, t) {
  const n = gt(e), o = Ht(e), r = n.visualViewport;
  let s = o.clientWidth, a = o.clientHeight, i = 0, l = 0;
  if (r) {
    s = r.width, a = r.height;
    const u = oa();
    (!u || u && t === "fixed") && (i = r.offsetLeft, l = r.offsetTop);
  }
  return {
    width: s,
    height: a,
    x: i,
    y: l
  };
}
function bh(e, t) {
  const n = Tn(e, !0, t === "fixed"), o = n.top + e.clientTop, r = n.left + e.clientLeft, s = Nt(e) ? Yn(e) : Lt(1), a = e.clientWidth * s.x, i = e.clientHeight * s.y, l = r * s.x, u = o * s.y;
  return {
    width: a,
    height: i,
    x: l,
    y: u
  };
}
function vi(e, t, n) {
  let o;
  if (t === "viewport")
    o = yh(e, n);
  else if (t === "document")
    o = vh(Ht(e));
  else if (Ot(t))
    o = bh(t, n);
  else {
    const r = cu(e);
    o = {
      x: t.x - r.x,
      y: t.y - r.y,
      width: t.width,
      height: t.height
    };
  }
  return ir(o);
}
function fu(e, t) {
  const n = yn(e);
  return n === t || !Ot(n) || qn(n) ? !1 : Bt(n).position === "fixed" || fu(n, t);
}
function wh(e, t) {
  const n = t.get(e);
  if (n)
    return n;
  let o = $o(e, [], !1).filter((i) => Ot(i) && En(i) !== "body"), r = null;
  const s = Bt(e).position === "fixed";
  let a = s ? yn(e) : e;
  for (; Ot(a) && !qn(a); ) {
    const i = Bt(a), l = na(a);
    !l && i.position === "fixed" && (r = null), (s ? !l && !r : !l && i.position === "static" && !!r && ["absolute", "fixed"].includes(r.position) || ko(a) && !l && fu(e, a)) ? o = o.filter((c) => c !== a) : r = i, a = yn(a);
  }
  return t.set(e, o), o;
}
function _h(e) {
  let {
    element: t,
    boundary: n,
    rootBoundary: o,
    strategy: r
  } = e;
  const a = [...n === "clippingAncestors" ? Er(t) ? [] : wh(t, this._c) : [].concat(n), o], i = a[0], l = a.reduce((u, c) => {
    const f = vi(t, c, r);
    return u.top = ft(f.top, u.top), u.right = gn(f.right, u.right), u.bottom = gn(f.bottom, u.bottom), u.left = ft(f.left, u.left), u;
  }, vi(t, i, r));
  return {
    width: l.right - l.left,
    height: l.bottom - l.top,
    x: l.left,
    y: l.top
  };
}
function xh(e) {
  const {
    width: t,
    height: n
  } = uu(e);
  return {
    width: t,
    height: n
  };
}
function Ch(e, t, n) {
  const o = Nt(t), r = Ht(t), s = n === "fixed", a = Tn(e, !0, s, t);
  let i = {
    scrollLeft: 0,
    scrollTop: 0
  };
  const l = Lt(0);
  if (o || !o && !s)
    if ((En(t) !== "body" || ko(r)) && (i = Ar(t)), o) {
      const p = Tn(t, !0, s, t);
      l.x = p.x + t.clientLeft, l.y = p.y + t.clientTop;
    } else r && (l.x = sa(r));
  const u = r && !o && !s ? du(r, i) : Lt(0), c = a.left + i.scrollLeft - l.x - u.x, f = a.top + i.scrollTop - l.y - u.y;
  return {
    x: c,
    y: f,
    width: a.width,
    height: a.height
  };
}
function Jr(e) {
  return Bt(e).position === "static";
}
function yi(e, t) {
  if (!Nt(e) || Bt(e).position === "fixed")
    return null;
  if (t)
    return t(e);
  let n = e.offsetParent;
  return Ht(e) === n && (n = n.ownerDocument.body), n;
}
function pu(e, t) {
  const n = gt(e);
  if (Er(e))
    return n;
  if (!Nt(e)) {
    let r = yn(e);
    for (; r && !qn(r); ) {
      if (Ot(r) && !Jr(r))
        return r;
      r = yn(r);
    }
    return n;
  }
  let o = yi(e, t);
  for (; o && dh(o) && Jr(o); )
    o = yi(o, t);
  return o && qn(o) && Jr(o) && !na(o) ? n : o || fh(e) || n;
}
const Sh = async function(e) {
  const t = this.getOffsetParent || pu, n = this.getDimensions, o = await n(e.floating);
  return {
    reference: Ch(e.reference, await t(e.floating), e.strategy),
    floating: {
      x: 0,
      y: 0,
      width: o.width,
      height: o.height
    }
  };
};
function $h(e) {
  return Bt(e).direction === "rtl";
}
const Th = {
  convertOffsetParentRelativeRectToViewportRelativeRect: mh,
  getDocumentElement: Ht,
  getClippingRect: _h,
  getOffsetParent: pu,
  getElementRects: Sh,
  getClientRects: gh,
  getDimensions: xh,
  getScale: Yn,
  isElement: Ot,
  isRTL: $h
};
function hu(e, t) {
  return e.x === t.x && e.y === t.y && e.width === t.width && e.height === t.height;
}
function Ph(e, t) {
  let n = null, o;
  const r = Ht(e);
  function s() {
    var i;
    clearTimeout(o), (i = n) == null || i.disconnect(), n = null;
  }
  function a(i, l) {
    i === void 0 && (i = !1), l === void 0 && (l = 1), s();
    const u = e.getBoundingClientRect(), {
      left: c,
      top: f,
      width: p,
      height: h
    } = u;
    if (i || t(), !p || !h)
      return;
    const g = Vo(f), y = Vo(r.clientWidth - (c + p)), C = Vo(r.clientHeight - (f + h)), v = Vo(c), m = {
      rootMargin: -g + "px " + -y + "px " + -C + "px " + -v + "px",
      threshold: ft(0, gn(1, l)) || 1
    };
    let x = !0;
    function A(R) {
      const I = R[0].intersectionRatio;
      if (I !== l) {
        if (!x)
          return a();
        I ? a(!1, I) : o = setTimeout(() => {
          a(!1, 1e-7);
        }, 1e3);
      }
      I === 1 && !hu(u, e.getBoundingClientRect()) && a(), x = !1;
    }
    try {
      n = new IntersectionObserver(A, {
        ...m,
        // Handle <iframe>s
        root: r.ownerDocument
      });
    } catch {
      n = new IntersectionObserver(A, m);
    }
    n.observe(e);
  }
  return a(!0), s;
}
function Eh(e, t, n, o) {
  o === void 0 && (o = {});
  const {
    ancestorScroll: r = !0,
    ancestorResize: s = !0,
    elementResize: a = typeof ResizeObserver == "function",
    layoutShift: i = typeof IntersectionObserver == "function",
    animationFrame: l = !1
  } = o, u = ra(e), c = r || s ? [...u ? $o(u) : [], ...$o(t)] : [];
  c.forEach((v) => {
    r && v.addEventListener("scroll", n, {
      passive: !0
    }), s && v.addEventListener("resize", n);
  });
  const f = u && i ? Ph(u, n) : null;
  let p = -1, h = null;
  a && (h = new ResizeObserver((v) => {
    let [w] = v;
    w && w.target === u && h && (h.unobserve(t), cancelAnimationFrame(p), p = requestAnimationFrame(() => {
      var m;
      (m = h) == null || m.observe(t);
    })), n();
  }), u && !l && h.observe(u), h.observe(t));
  let g, y = l ? Tn(e) : null;
  l && C();
  function C() {
    const v = Tn(e);
    y && !hu(y, v) && n(), y = v, g = requestAnimationFrame(C);
  }
  return n(), () => {
    var v;
    c.forEach((w) => {
      r && w.removeEventListener("scroll", n), s && w.removeEventListener("resize", n);
    }), f == null || f(), (v = h) == null || v.disconnect(), h = null, l && cancelAnimationFrame(g);
  };
}
const Ah = ih, Oh = lh, bi = rh, Bh = ch, kh = sh, Mh = oh, Dh = uh, Rh = (e, t, n) => {
  const o = /* @__PURE__ */ new Map(), r = {
    platform: Th,
    ...n
  }, s = {
    ...r.platform,
    _c: o
  };
  return nh(e, t, {
    ...r,
    platform: s
  });
};
function Ih(e) {
  return e != null && typeof e == "object" && "$el" in e;
}
function Cs(e) {
  if (Ih(e)) {
    const t = e.$el;
    return ta(t) && En(t) === "#comment" ? null : t;
  }
  return e;
}
function Nn(e) {
  return typeof e == "function" ? e() : d(e);
}
function Lh(e) {
  return {
    name: "arrow",
    options: e,
    fn(t) {
      const n = Cs(Nn(e.element));
      return n == null ? {} : Mh({
        element: n,
        padding: e.padding
      }).fn(t);
    }
  };
}
function mu(e) {
  return typeof window > "u" ? 1 : (e.ownerDocument.defaultView || window).devicePixelRatio || 1;
}
function wi(e, t) {
  const n = mu(e);
  return Math.round(t * n) / n;
}
function Fh(e, t, n) {
  n === void 0 && (n = {});
  const o = n.whileElementsMounted, r = O(() => {
    var I;
    return (I = Nn(n.open)) != null ? I : !0;
  }), s = O(() => Nn(n.middleware)), a = O(() => {
    var I;
    return (I = Nn(n.placement)) != null ? I : "bottom";
  }), i = O(() => {
    var I;
    return (I = Nn(n.strategy)) != null ? I : "absolute";
  }), l = O(() => {
    var I;
    return (I = Nn(n.transform)) != null ? I : !0;
  }), u = O(() => Cs(e.value)), c = O(() => Cs(t.value)), f = B(0), p = B(0), h = B(i.value), g = B(a.value), y = wr({}), C = B(!1), v = O(() => {
    const I = {
      position: h.value,
      left: "0",
      top: "0"
    };
    if (!c.value)
      return I;
    const H = wi(c.value, f.value), k = wi(c.value, p.value);
    return l.value ? {
      ...I,
      transform: "translate(" + H + "px, " + k + "px)",
      ...mu(c.value) >= 1.5 && {
        willChange: "transform"
      }
    } : {
      position: h.value,
      left: H + "px",
      top: k + "px"
    };
  });
  let w;
  function m() {
    if (u.value == null || c.value == null)
      return;
    const I = r.value;
    Rh(u.value, c.value, {
      middleware: s.value,
      placement: a.value,
      strategy: i.value
    }).then((H) => {
      f.value = H.x, p.value = H.y, h.value = H.strategy, g.value = H.placement, y.value = H.middlewareData, C.value = I !== !1;
    });
  }
  function x() {
    typeof w == "function" && (w(), w = void 0);
  }
  function A() {
    if (x(), o === void 0) {
      m();
      return;
    }
    if (u.value != null && c.value != null) {
      w = o(u.value, c.value, m);
      return;
    }
  }
  function R() {
    r.value || (C.value = !1);
  }
  return be([s, a, i, r], m, {
    flush: "sync"
  }), be([u, c], A, {
    flush: "sync"
  }), be(r, R, {
    flush: "sync"
  }), Vs() && Ui(x), {
    x: Rn(f),
    y: Rn(p),
    strategy: Rn(h),
    placement: Rn(g),
    middlewareData: Rn(y),
    isPositioned: Rn(C),
    floatingStyles: v,
    update: m
  };
}
function aa(e) {
  return e ? e.flatMap((t) => t.type === ke ? aa(t.children) : [t]) : [];
}
const Ss = /* @__PURE__ */ T({
  name: "PrimitiveSlot",
  inheritAttrs: !1,
  setup(e, { attrs: t, slots: n }) {
    return () => {
      var l, u;
      if (!n.default)
        return null;
      const o = aa(n.default()), r = o.findIndex((c) => c.type !== Vt);
      if (r === -1)
        return o;
      const s = o[r];
      (l = s.props) == null || delete l.ref;
      const a = s.props ? z(t, s.props) : t;
      t.class && ((u = s.props) != null && u.class) && delete s.props.class;
      const i = mn(s, a);
      for (const c in a)
        c.startsWith("on") && (i.props || (i.props = {}), i.props[c] = a[c]);
      return o.length === 1 ? i : (o[r] = i, o);
    };
  }
}), ie = /* @__PURE__ */ T({
  name: "Primitive",
  inheritAttrs: !1,
  props: {
    asChild: {
      type: Boolean,
      default: !1
    },
    as: {
      type: [String, Object],
      default: "div"
    }
  },
  setup(e, { attrs: t, slots: n }) {
    const o = e.asChild ? "template" : e.as;
    return typeof o == "string" && ["area", "img", "input"].includes(o) ? () => St(o, t) : o !== "template" ? () => St(e.as, t, { default: n.default }) : () => St(Ss, t, { default: n.default });
  }
}), ia = /* @__PURE__ */ T({
  __name: "VisuallyHidden",
  props: {
    feature: { default: "focusable" },
    asChild: { type: Boolean },
    as: { default: "span" }
  },
  setup(e) {
    return (t, n) => (_(), E(d(ie), {
      as: t.as,
      "as-child": t.asChild,
      "aria-hidden": t.feature === "focusable" ? "true" : void 0,
      "data-hidden": t.feature === "fully-hidden" ? "" : void 0,
      tabindex: t.feature === "fully-hidden" ? "-1" : void 0,
      style: {
        // See: https://github.com/twbs/bootstrap/blob/master/scss/mixins/_screen-reader.scss
        position: "absolute",
        border: 0,
        width: "1px",
        height: "1px",
        padding: 0,
        margin: "-1px",
        overflow: "hidden",
        clip: "rect(0, 0, 0, 0)",
        clipPath: "inset(50%)",
        whiteSpace: "nowrap",
        wordWrap: "normal"
      }
    }, {
      default: S(() => [
        P(t.$slots, "default")
      ]),
      _: 3
    }, 8, ["as", "as-child", "aria-hidden", "data-hidden", "tabindex"]));
  }
});
function Vh(e, t) {
  var n;
  const o = wr();
  return Me(() => {
    o.value = e();
  }, {
    ...t,
    flush: (n = void 0) != null ? n : "sync"
  }), yr(o);
}
function eo(e) {
  return Vs() ? (Ui(e), !0) : !1;
}
function Nh() {
  const e = /* @__PURE__ */ new Set(), t = (s) => {
    e.delete(s);
  };
  return {
    on: (s) => {
      e.add(s);
      const a = () => t(s);
      return eo(a), {
        off: a
      };
    },
    off: t,
    trigger: (...s) => Promise.all(Array.from(e).map((a) => a(...s))),
    clear: () => {
      e.clear();
    }
  };
}
function zh(e) {
  let t = !1, n;
  const o = Ki(!0);
  return (...r) => (t || (n = o.run(() => e(...r)), t = !0), n);
}
function gu(e) {
  let t = 0, n, o;
  const r = () => {
    t -= 1, o && t <= 0 && (o.stop(), n = void 0, o = void 0);
  };
  return (...s) => (t += 1, o || (o = Ki(!0), n = o.run(() => e(...s))), eo(r), n);
}
const on = typeof window < "u" && typeof document < "u";
typeof WorkerGlobalScope < "u" && globalThis instanceof WorkerGlobalScope;
const jh = (e) => typeof e < "u", Hh = Object.prototype.toString, Wh = (e) => Hh.call(e) === "[object Object]", _i = () => {
}, xi = /* @__PURE__ */ Kh();
function Kh() {
  var e, t;
  return on && ((e = window == null ? void 0 : window.navigator) == null ? void 0 : e.userAgent) && (/iP(?:ad|hone|od)/.test(window.navigator.userAgent) || ((t = window == null ? void 0 : window.navigator) == null ? void 0 : t.maxTouchPoints) > 2 && /iPad|Macintosh/.test(window == null ? void 0 : window.navigator.userAgent));
}
function Uh(e, t) {
  function n(...o) {
    return new Promise((r, s) => {
      Promise.resolve(e(() => t.apply(this, o), { fn: t, thisArg: this, args: o })).then(r).catch(s);
    });
  }
  return n;
}
function Gh(e, t = {}) {
  let n, o, r = _i;
  const s = (l) => {
    clearTimeout(l), r(), r = _i;
  };
  let a;
  return (l) => {
    const u = mt(e), c = mt(t.maxWait);
    return n && s(n), u <= 0 || c !== void 0 && c <= 0 ? (o && (s(o), o = null), Promise.resolve(l())) : new Promise((f, p) => {
      r = t.rejectOnCancel ? p : f, a = l, c && !o && (o = setTimeout(() => {
        n && s(n), o = null, f(a());
      }, c)), n = setTimeout(() => {
        o && s(o), o = null, f(l());
      }, u);
    });
  };
}
function Yh(e) {
  return jt();
}
function Zr(e) {
  return Array.isArray(e) ? e : [e];
}
function vu(e, t = 1e4) {
  return td((n, o) => {
    let r = mt(e), s;
    const a = () => setTimeout(() => {
      r = mt(e), o();
    }, mt(t));
    return eo(() => {
      clearTimeout(s);
    }), {
      get() {
        return n(), r;
      },
      set(i) {
        r = i, o(), clearTimeout(s), s = a();
      }
    };
  });
}
function yu(e, t = 200, n = {}) {
  return Uh(
    Gh(t, n),
    e
  );
}
const qh = mt;
function Xh(e, t) {
  Yh() && Ao(e, t);
}
function bu(e, t, n = {}) {
  const {
    immediate: o = !0,
    immediateCallback: r = !1
  } = n, s = wr(!1);
  let a = null;
  function i() {
    a && (clearTimeout(a), a = null);
  }
  function l() {
    s.value = !1, i();
  }
  function u(...c) {
    r && e(), i(), s.value = !0, a = setTimeout(() => {
      s.value = !1, a = null, e(...c);
    }, mt(t));
  }
  return o && (s.value = !0, on && u()), eo(l), {
    isPending: yr(s),
    start: u,
    stop: l
  };
}
function Jh(e, t, n) {
  return be(
    e,
    t,
    {
      ...n,
      immediate: !0
    }
  );
}
function Zh(e, t, n) {
  const o = be(e, (...r) => (Le(() => o()), t(...r)), n);
  return o;
}
const Or = on ? window : void 0;
function zt(e) {
  var t;
  const n = mt(e);
  return (t = n == null ? void 0 : n.$el) != null ? t : n;
}
function Xn(...e) {
  const t = [], n = () => {
    t.forEach((i) => i()), t.length = 0;
  }, o = (i, l, u, c) => (i.addEventListener(l, u, c), () => i.removeEventListener(l, u, c)), r = O(() => {
    const i = Zr(mt(e[0])).filter((l) => l != null);
    return i.every((l) => typeof l != "string") ? i : void 0;
  }), s = Jh(
    () => {
      var i, l;
      return [
        (l = (i = r.value) == null ? void 0 : i.map((u) => zt(u))) != null ? l : [Or].filter((u) => u != null),
        Zr(mt(r.value ? e[1] : e[0])),
        Zr(d(r.value ? e[2] : e[1])),
        // @ts-expect-error - TypeScript gets the correct types, but somehow still complains
        mt(r.value ? e[3] : e[2])
      ];
    },
    ([i, l, u, c]) => {
      if (n(), !(i != null && i.length) || !(l != null && l.length) || !(u != null && u.length))
        return;
      const f = Wh(c) ? { ...c } : c;
      t.push(
        ...i.flatMap(
          (p) => l.flatMap(
            (h) => u.map((g) => o(p, h, g, f))
          )
        )
      );
    },
    { flush: "post" }
  ), a = () => {
    s(), n();
  };
  return eo(n), a;
}
function wu() {
  const e = wr(!1), t = jt();
  return t && Se(() => {
    e.value = !0;
  }, t), e;
}
function Qh(e) {
  const t = wu();
  return O(() => (t.value, !!e()));
}
function em(e) {
  return typeof e == "function" ? e : typeof e == "string" ? (t) => t.key === e : Array.isArray(e) ? (t) => e.includes(t.key) : () => !0;
}
function tm(...e) {
  let t, n, o = {};
  e.length === 3 ? (t = e[0], n = e[1], o = e[2]) : e.length === 2 ? typeof e[1] == "object" ? (t = !0, n = e[0], o = e[1]) : (t = e[0], n = e[1]) : (t = !0, n = e[0]);
  const {
    target: r = Or,
    eventName: s = "keydown",
    passive: a = !1,
    dedupe: i = !1
  } = o, l = em(t);
  return Xn(r, s, (c) => {
    c.repeat && mt(i) || l(c) && n(c);
  }, a);
}
function nm(e) {
  return JSON.parse(JSON.stringify(e));
}
function Pn(e, t, n = {}) {
  const { window: o = Or, ...r } = n;
  let s;
  const a = Qh(() => o && "ResizeObserver" in o), i = () => {
    s && (s.disconnect(), s = void 0);
  }, l = O(() => {
    const f = mt(e);
    return Array.isArray(f) ? f.map((p) => zt(p)) : [zt(f)];
  }), u = be(
    l,
    (f) => {
      if (i(), a.value && o) {
        s = new ResizeObserver(t);
        for (const p of f)
          p && s.observe(p, r);
      }
    },
    { immediate: !0, flush: "post" }
  ), c = () => {
    i(), u();
  };
  return eo(c), {
    isSupported: a,
    stop: c
  };
}
function vt(e, t, n, o = {}) {
  var r, s, a;
  const {
    clone: i = !1,
    passive: l = !1,
    eventName: u,
    deep: c = !1,
    defaultValue: f,
    shouldEmit: p
  } = o, h = jt(), g = n || (h == null ? void 0 : h.emit) || ((r = h == null ? void 0 : h.$emit) == null ? void 0 : r.bind(h)) || ((a = (s = h == null ? void 0 : h.proxy) == null ? void 0 : s.$emit) == null ? void 0 : a.bind(h == null ? void 0 : h.proxy));
  let y = u;
  t || (t = "modelValue"), y = y || `update:${t.toString()}`;
  const C = (m) => i ? typeof i == "function" ? i(m) : nm(m) : m, v = () => jh(e[t]) ? C(e[t]) : f, w = (m) => {
    p ? p(m) && g(y, m) : g(y, m);
  };
  if (l) {
    const m = v(), x = B(m);
    let A = !1;
    return be(
      () => e[t],
      (R) => {
        A || (A = !0, x.value = C(R), Le(() => A = !1));
      }
    ), be(
      x,
      (R) => {
        !A && (R !== e[t] || c) && w(R);
      },
      { deep: c }
    ), x;
  } else
    return O({
      get() {
        return v();
      },
      set(m) {
        w(m);
      }
    });
}
function Ci(e) {
  return typeof e == "string" ? `'${e}'` : new om().serialize(e);
}
const om = /* @__PURE__ */ function() {
  var t;
  class e {
    constructor() {
      Ta(this, t, /* @__PURE__ */ new Map());
    }
    compare(o, r) {
      const s = typeof o, a = typeof r;
      return s === "string" && a === "string" ? o.localeCompare(r) : s === "number" && a === "number" ? o - r : String.prototype.localeCompare.call(this.serialize(o, !0), this.serialize(r, !0));
    }
    serialize(o, r) {
      if (o === null) return "null";
      switch (typeof o) {
        case "string":
          return r ? o : `'${o}'`;
        case "bigint":
          return `${o}n`;
        case "object":
          return this.$object(o);
        case "function":
          return this.$function(o);
      }
      return String(o);
    }
    serializeObject(o) {
      const r = Object.prototype.toString.call(o);
      if (r !== "[object Object]") return this.serializeBuiltInType(r.length < 10 ? `unknown:${r}` : r.slice(8, -1), o);
      const s = o.constructor, a = s === Object || s === void 0 ? "" : s.name;
      if (a !== "" && globalThis[a] === s) return this.serializeBuiltInType(a, o);
      if (typeof o.toJSON == "function") {
        const i = o.toJSON();
        return a + (i !== null && typeof i == "object" ? this.$object(i) : `(${this.serialize(i)})`);
      }
      return this.serializeObjectEntries(a, Object.entries(o));
    }
    serializeBuiltInType(o, r) {
      const s = this["$" + o];
      if (s) return s.call(this, r);
      if (typeof (r == null ? void 0 : r.entries) == "function") return this.serializeObjectEntries(o, r.entries());
      throw new Error(`Cannot serialize ${o}`);
    }
    serializeObjectEntries(o, r) {
      const s = Array.from(r).sort((i, l) => this.compare(i[0], l[0]));
      let a = `${o}{`;
      for (let i = 0; i < s.length; i++) {
        const [l, u] = s[i];
        a += `${this.serialize(l, !0)}:${this.serialize(u)}`, i < s.length - 1 && (a += ",");
      }
      return a + "}";
    }
    $object(o) {
      let r = oo(this, t).get(o);
      return r === void 0 && (oo(this, t).set(o, `#${oo(this, t).size}`), r = this.serializeObject(o), oo(this, t).set(o, r)), r;
    }
    $function(o) {
      const r = Function.prototype.toString.call(o);
      return r.slice(-15) === "[native code] }" ? `${o.name || ""}()[native]` : `${o.name}(${o.length})${r.replace(/\s*\n\s*/g, "")}`;
    }
    $Array(o) {
      let r = "[";
      for (let s = 0; s < o.length; s++) r += this.serialize(o[s]), s < o.length - 1 && (r += ",");
      return r + "]";
    }
    $Date(o) {
      try {
        return `Date(${o.toISOString()})`;
      } catch {
        return "Date(null)";
      }
    }
    $ArrayBuffer(o) {
      return `ArrayBuffer[${new Uint8Array(o).join(",")}]`;
    }
    $Set(o) {
      return `Set${this.$Array(Array.from(o).sort((r, s) => this.compare(r, s)))}`;
    }
    $Map(o) {
      return this.serializeObjectEntries("Map", o.entries());
    }
  }
  t = new WeakMap();
  for (const n of ["Error", "RegExp", "URL"]) e.prototype["$" + n] = function(o) {
    return `${n}(${o})`;
  };
  for (const n of ["Int8Array", "Uint8Array", "Uint8ClampedArray", "Int16Array", "Uint16Array", "Int32Array", "Uint32Array", "Float32Array", "Float64Array"]) e.prototype["$" + n] = function(o) {
    return `${n}[${o.join(",")}]`;
  };
  for (const n of ["BigInt64Array", "BigUint64Array"]) e.prototype["$" + n] = function(o) {
    return `${n}[${o.join("n,")}${o.length > 0 ? "n" : ""}]`;
  };
  return e;
}();
function rm(e, t) {
  return e === t || Ci(e) === Ci(t);
}
function Si(e) {
  return e == null;
}
function Ve(e, t) {
  const n = typeof e == "string" && !t ? `${e}Context` : t, o = Symbol(n);
  return [(a) => {
    const i = Zt(o, a);
    if (i || i === null)
      return i;
    throw new Error(
      `Injection \`${o.toString()}\` not found. Component must be used within ${Array.isArray(e) ? `one of the following components: ${e.join(
        ", "
      )}` : `\`${e}\``}`
    );
  }, (a) => (Jn(o, a), a)];
}
const [Br, I0] = Ve("ConfigProvider");
function to(e) {
  const t = Br({
    dir: B("ltr")
  });
  return O(() => {
    var n;
    return (e == null ? void 0 : e.value) || ((n = t.dir) == null ? void 0 : n.value) || "ltr";
  });
}
function X() {
  const e = jt(), t = B(), n = O(() => {
    var a, i;
    return ["#text", "#comment"].includes((a = t.value) == null ? void 0 : a.$el.nodeName) ? (i = t.value) == null ? void 0 : i.$el.nextElementSibling : zt(t);
  }), o = Object.assign({}, e.exposed), r = {};
  for (const a in e.props)
    Object.defineProperty(r, a, {
      enumerable: !0,
      configurable: !0,
      get: () => e.props[a]
    });
  if (Object.keys(o).length > 0)
    for (const a in o)
      Object.defineProperty(r, a, {
        enumerable: !0,
        configurable: !0,
        get: () => o[a]
      });
  Object.defineProperty(r, "$el", {
    enumerable: !0,
    configurable: !0,
    get: () => e.vnode.el
  }), e.exposed = r;
  function s(a) {
    t.value = a, a && (Object.defineProperty(r, "$el", {
      enumerable: !0,
      configurable: !0,
      get: () => a instanceof Element ? a : a.$el
    }), e.exposed = r);
  }
  return { forwardRef: s, currentRef: t, currentElement: n };
}
const sm = ["INPUT", "TEXTAREA"];
function am(e, t, n, o = {}) {
  if (!t || o.enableIgnoredElement && sm.includes(t.nodeName))
    return null;
  const {
    arrowKeyOptions: r = "both",
    attributeName: s = "[data-reka-collection-item]",
    itemsArray: a = [],
    loop: i = !0,
    dir: l = "ltr",
    preventScroll: u = !0,
    focus: c = !1
  } = o, [f, p, h, g, y, C] = [
    e.key === "ArrowRight",
    e.key === "ArrowLeft",
    e.key === "ArrowUp",
    e.key === "ArrowDown",
    e.key === "Home",
    e.key === "End"
  ], v = h || g, w = f || p;
  if (!y && !C && (!v && !w || r === "vertical" && w || r === "horizontal" && v))
    return null;
  const m = n ? Array.from(n.querySelectorAll(s)) : a;
  if (!m.length)
    return null;
  u && e.preventDefault();
  let x = null;
  return w || v ? x = _u(m, t, {
    goForward: v ? g : l === "ltr" ? f : p,
    loop: i
  }) : y ? x = m.at(0) || null : C && (x = m.at(-1) || null), c && (x == null || x.focus()), x;
}
function _u(e, t, n, o = e.length) {
  if (--o === 0)
    return null;
  const r = e.indexOf(t), s = n.goForward ? r + 1 : r - 1;
  if (!n.loop && (s < 0 || s >= e.length))
    return null;
  const a = (s + e.length) % e.length, i = e[a];
  return i ? i.hasAttribute("disabled") && i.getAttribute("disabled") !== "false" ? _u(
    e,
    i,
    n,
    o
  ) : i : null;
}
let im = 0;
function yt(e, t = "reka") {
  const n = Br({ useId: void 0 });
  return ds ? `${t}-${ds()}` : n.useId ? `${t}-${n.useId()}` : `${t}-${++im}`;
}
function xu(e, t) {
  const n = B(e);
  function o(s) {
    return t[n.value][s] ?? n.value;
  }
  return {
    state: n,
    dispatch: (s) => {
      n.value = o(s);
    }
  };
}
function lm(e, t) {
  var C;
  const n = B({}), o = B("none"), r = B(e), s = e.value ? "mounted" : "unmounted";
  let a;
  const i = ((C = t.value) == null ? void 0 : C.ownerDocument.defaultView) ?? Or, { state: l, dispatch: u } = xu(s, {
    mounted: {
      UNMOUNT: "unmounted",
      ANIMATION_OUT: "unmountSuspended"
    },
    unmountSuspended: {
      MOUNT: "mounted",
      ANIMATION_END: "unmounted"
    },
    unmounted: {
      MOUNT: "mounted"
    }
  }), c = (v) => {
    var w;
    if (on) {
      const m = new CustomEvent(v, { bubbles: !1, cancelable: !1 });
      (w = t.value) == null || w.dispatchEvent(m);
    }
  };
  be(
    e,
    async (v, w) => {
      var x;
      const m = w !== v;
      if (await Le(), m) {
        const A = o.value, R = No(t.value);
        v ? (u("MOUNT"), c("enter"), R === "none" && c("after-enter")) : R === "none" || ((x = n.value) == null ? void 0 : x.display) === "none" ? (u("UNMOUNT"), c("leave"), c("after-leave")) : w && A !== R ? (u("ANIMATION_OUT"), c("leave")) : (u("UNMOUNT"), c("after-leave"));
      }
    },
    { immediate: !0 }
  );
  const f = (v) => {
    const w = No(t.value), m = w.includes(
      v.animationName
    ), x = l.value === "mounted" ? "enter" : "leave";
    if (v.target === t.value && m && (c(`after-${x}`), u("ANIMATION_END"), !r.value)) {
      const A = t.value.style.animationFillMode;
      t.value.style.animationFillMode = "forwards", a = i == null ? void 0 : i.setTimeout(() => {
        var R;
        ((R = t.value) == null ? void 0 : R.style.animationFillMode) === "forwards" && (t.value.style.animationFillMode = A);
      });
    }
    v.target === t.value && w === "none" && u("ANIMATION_END");
  }, p = (v) => {
    v.target === t.value && (o.value = No(t.value));
  }, h = be(
    t,
    (v, w) => {
      v ? (n.value = getComputedStyle(v), v.addEventListener("animationstart", p), v.addEventListener("animationcancel", f), v.addEventListener("animationend", f)) : (u("ANIMATION_END"), a !== void 0 && (i == null || i.clearTimeout(a)), w == null || w.removeEventListener("animationstart", p), w == null || w.removeEventListener("animationcancel", f), w == null || w.removeEventListener("animationend", f));
    },
    { immediate: !0 }
  ), g = be(l, () => {
    const v = No(t.value);
    o.value = l.value === "mounted" ? v : "none";
  });
  return bt(() => {
    h(), g();
  }), {
    isPresent: O(
      () => ["mounted", "unmountSuspended"].includes(l.value)
    )
  };
}
function No(e) {
  return e && getComputedStyle(e).animationName || "none";
}
const kt = /* @__PURE__ */ T({
  name: "Presence",
  props: {
    present: {
      type: Boolean,
      required: !0
    },
    forceMount: {
      type: Boolean
    }
  },
  slots: {},
  setup(e, { slots: t, expose: n }) {
    var u;
    const { present: o, forceMount: r } = Xe(e), s = B(), { isPresent: a } = lm(o, s);
    n({ present: a });
    let i = t.default({ present: a.value });
    i = aa(i || []);
    const l = jt();
    if (i && (i == null ? void 0 : i.length) > 1) {
      const c = (u = l == null ? void 0 : l.parent) != null && u.type.name ? `<${l.parent.type.name} />` : "component";
      throw new Error(
        [
          `Detected an invalid children for \`${c}\` for  \`Presence\` component.`,
          "",
          "Note: Presence works similarly to `v-if` directly, but it waits for animation/transition to finished before unmounting. So it expect only one direct child of valid VNode type.",
          "You can apply a few solutions:",
          [
            "Provide a single child element so that `presence` directive attach correctly.",
            "Ensure the first child is an actual element instead of a raw text node or comment node."
          ].map((f) => `  - ${f}`).join(`
`)
        ].join(`
`)
      );
    }
    return () => r.value || o.value || a.value ? St(t.default({ present: a.value })[0], {
      ref: (c) => {
        const f = zt(c);
        return typeof (f == null ? void 0 : f.hasAttribute) > "u" || (f != null && f.hasAttribute("data-reka-popper-content-wrapper") ? s.value = f.firstElementChild : s.value = f), f;
      }
    }) : null;
  }
});
function An(e) {
  const t = jt(), n = t == null ? void 0 : t.type.emits, o = {};
  return n != null && n.length || console.warn(
    `No emitted event found. Please check component: ${t == null ? void 0 : t.type.__name}`
  ), n == null || n.forEach((r) => {
    o[co(qe(r))] = (...s) => e(r, ...s);
  }), o;
}
function wt(e) {
  const t = jt(), n = Object.keys((t == null ? void 0 : t.type.props) ?? {}).reduce((r, s) => {
    const a = (t == null ? void 0 : t.type.props[s]).default;
    return a !== void 0 && (r[s] = a), r;
  }, {}), o = rd(e);
  return O(() => {
    const r = {}, s = (t == null ? void 0 : t.vnode.props) ?? {};
    return Object.keys(s).forEach((a) => {
      r[qe(a)] = s[a];
    }), Object.keys({ ...n, ...r }).reduce((a, i) => (o.value[i] !== void 0 && (a[i] = o.value[i]), a), {});
  });
}
function Ne(e, t) {
  const n = wt(e), o = t ? An(t) : {};
  return O(() => ({
    ...n.value,
    ...o
  }));
}
const [Wt, um] = Ve("DialogRoot"), cm = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "DialogRoot",
  props: {
    open: { type: Boolean, default: void 0 },
    defaultOpen: { type: Boolean, default: !1 },
    modal: { type: Boolean, default: !0 }
  },
  emits: ["update:open"],
  setup(e, { emit: t }) {
    const n = e, r = vt(n, "open", t, {
      defaultValue: n.defaultOpen,
      passive: n.open === void 0
    }), s = B(), a = B(), { modal: i } = Xe(n);
    return um({
      open: r,
      modal: i,
      openModal: () => {
        r.value = !0;
      },
      onOpenChange: (l) => {
        r.value = l;
      },
      onOpenToggle: () => {
        r.value = !r.value;
      },
      contentId: "",
      titleId: "",
      descriptionId: "",
      triggerElement: s,
      contentElement: a
    }), (l, u) => P(l.$slots, "default", { open: d(r) });
  }
}), dm = /* @__PURE__ */ T({
  __name: "DialogTrigger",
  props: {
    asChild: { type: Boolean },
    as: { default: "button" }
  },
  setup(e) {
    const t = e, n = Wt(), { forwardRef: o, currentElement: r } = X();
    return n.contentId || (n.contentId = yt(void 0, "reka-dialog-content")), Se(() => {
      n.triggerElement.value = r.value;
    }), (s, a) => (_(), E(d(ie), z(t, {
      ref: d(o),
      type: s.as === "button" ? "button" : void 0,
      "aria-haspopup": "dialog",
      "aria-expanded": d(n).open.value || !1,
      "aria-controls": d(n).open.value ? d(n).contentId : void 0,
      "data-state": d(n).open.value ? "open" : "closed",
      onClick: d(n).onOpenToggle
    }), {
      default: S(() => [
        P(s.$slots, "default")
      ]),
      _: 3
    }, 16, ["type", "aria-expanded", "aria-controls", "data-state", "onClick"]));
  }
}), kr = /* @__PURE__ */ T({
  __name: "Teleport",
  props: {
    to: { default: "body" },
    disabled: { type: Boolean },
    defer: { type: Boolean },
    forceMount: { type: Boolean }
  },
  setup(e) {
    const t = wu();
    return (n, o) => d(t) || n.forceMount ? (_(), E(wl, {
      key: 0,
      to: n.to,
      disabled: n.disabled,
      defer: n.defer
    }, [
      P(n.$slots, "default")
    ], 8, ["to", "disabled", "defer"])) : Be("", !0);
  }
});
function la(e, t, n) {
  const o = n.originalEvent.target, r = new CustomEvent(e, {
    bubbles: !1,
    cancelable: !0,
    detail: n
  });
  t && o.addEventListener(e, t, { once: !0 }), o.dispatchEvent(r);
}
const fm = "dismissableLayer.pointerDownOutside", pm = "dismissableLayer.focusOutside";
function Cu(e, t) {
  const n = t.closest(
    "[data-dismissable-layer]"
  ), o = e.dataset.dismissableLayer === "" ? e : e.querySelector(
    "[data-dismissable-layer]"
  ), r = Array.from(
    e.ownerDocument.querySelectorAll("[data-dismissable-layer]")
  );
  return !!(n && o === n || r.indexOf(o) < r.indexOf(n));
}
function hm(e, t) {
  var s;
  const n = ((s = t == null ? void 0 : t.value) == null ? void 0 : s.ownerDocument) ?? (globalThis == null ? void 0 : globalThis.document), o = B(!1), r = B(() => {
  });
  return Me((a) => {
    if (!on)
      return;
    const i = async (u) => {
      const c = u.target;
      if (t != null && t.value) {
        if (Cu(t.value, c)) {
          o.value = !1;
          return;
        }
        if (u.target && !o.value) {
          let f = function() {
            la(
              fm,
              e,
              p
            );
          };
          const p = { originalEvent: u };
          u.pointerType === "touch" ? (n.removeEventListener("click", r.value), r.value = f, n.addEventListener("click", r.value, {
            once: !0
          })) : f();
        } else
          n.removeEventListener("click", r.value);
        o.value = !1;
      }
    }, l = window.setTimeout(() => {
      n.addEventListener("pointerdown", i);
    }, 0);
    a(() => {
      window.clearTimeout(l), n.removeEventListener("pointerdown", i), n.removeEventListener("click", r.value);
    });
  }), {
    onPointerDownCapture: () => o.value = !0
  };
}
function mm(e, t) {
  var r;
  const n = ((r = t == null ? void 0 : t.value) == null ? void 0 : r.ownerDocument) ?? (globalThis == null ? void 0 : globalThis.document), o = B(!1);
  return Me((s) => {
    if (!on)
      return;
    const a = async (i) => {
      t != null && t.value && (await Le(), !(!t.value || Cu(t.value, i.target)) && i.target && !o.value && la(
        pm,
        e,
        { originalEvent: i }
      ));
    };
    n.addEventListener("focusin", a), s(() => n.removeEventListener("focusin", a));
  }), {
    onFocusCapture: () => o.value = !0,
    onBlurCapture: () => o.value = !1
  };
}
const Yt = Po({
  layersRoot: /* @__PURE__ */ new Set(),
  layersWithOutsidePointerEventsDisabled: /* @__PURE__ */ new Set(),
  branches: /* @__PURE__ */ new Set()
}), Mr = /* @__PURE__ */ T({
  __name: "DismissableLayer",
  props: {
    disableOutsidePointerEvents: { type: Boolean, default: !1 },
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "dismiss"],
  setup(e, { emit: t }) {
    const n = e, o = t, { forwardRef: r, currentElement: s } = X(), a = O(
      () => {
        var g;
        return ((g = s.value) == null ? void 0 : g.ownerDocument) ?? globalThis.document;
      }
    ), i = O(() => Yt.layersRoot), l = O(() => s.value ? Array.from(i.value).indexOf(s.value) : -1), u = O(() => Yt.layersWithOutsidePointerEventsDisabled.size > 0), c = O(() => {
      const g = Array.from(i.value), [y] = [...Yt.layersWithOutsidePointerEventsDisabled].slice(-1), C = g.indexOf(y);
      return l.value >= C;
    }), f = hm(async (g) => {
      const y = [...Yt.branches].some(
        (C) => C == null ? void 0 : C.contains(g.target)
      );
      !c.value || y || (o("pointerDownOutside", g), o("interactOutside", g), await Le(), g.defaultPrevented || o("dismiss"));
    }, s), p = mm((g) => {
      [...Yt.branches].some(
        (C) => C == null ? void 0 : C.contains(g.target)
      ) || (o("focusOutside", g), o("interactOutside", g), g.defaultPrevented || o("dismiss"));
    }, s);
    tm("Escape", (g) => {
      l.value === i.value.size - 1 && (o("escapeKeyDown", g), g.defaultPrevented || o("dismiss"));
    });
    let h;
    return Me((g) => {
      s.value && (n.disableOutsidePointerEvents && (Yt.layersWithOutsidePointerEventsDisabled.size === 0 && (h = a.value.body.style.pointerEvents, a.value.body.style.pointerEvents = "none"), Yt.layersWithOutsidePointerEventsDisabled.add(s.value)), i.value.add(s.value), g(() => {
        n.disableOutsidePointerEvents && Yt.layersWithOutsidePointerEventsDisabled.size === 1 && (a.value.body.style.pointerEvents = h);
      }));
    }), Me((g) => {
      g(() => {
        s.value && (i.value.delete(s.value), Yt.layersWithOutsidePointerEventsDisabled.delete(s.value));
      });
    }), (g, y) => (_(), E(d(ie), {
      ref: d(r),
      "as-child": g.asChild,
      as: g.as,
      "data-dismissable-layer": "",
      style: nt({
        pointerEvents: u.value ? c.value ? "auto" : "none" : void 0
      }),
      onFocusCapture: d(p).onFocusCapture,
      onBlurCapture: d(p).onBlurCapture,
      onPointerdownCapture: d(f).onPointerDownCapture
    }, {
      default: S(() => [
        P(g.$slots, "default")
      ]),
      _: 3
    }, 8, ["as-child", "as", "style", "onFocusCapture", "onBlurCapture", "onPointerdownCapture"]));
  }
});
function ot() {
  let e = document.activeElement;
  if (e == null)
    return null;
  for (; e != null && e.shadowRoot != null && e.shadowRoot.activeElement != null; )
    e = e.shadowRoot.activeElement;
  return e;
}
const gm = "menu.itemSelect", $s = ["Enter", " "], vm = ["ArrowDown", "PageUp", "Home"], Su = ["ArrowUp", "PageDown", "End"], ym = [...vm, ...Su], bm = {
  ltr: [...$s, "ArrowRight"],
  rtl: [...$s, "ArrowLeft"]
}, wm = {
  ltr: ["ArrowLeft"],
  rtl: ["ArrowRight"]
};
function ua(e) {
  return e ? "open" : "closed";
}
function lr(e) {
  return e === "indeterminate";
}
function ca(e) {
  return lr(e) ? "indeterminate" : e ? "checked" : "unchecked";
}
function Ts(e) {
  const t = ot();
  for (const n of e)
    if (n === t || (n.focus(), ot() !== t))
      return;
}
function _m(e, t) {
  const { x: n, y: o } = e;
  let r = !1;
  for (let s = 0, a = t.length - 1; s < t.length; a = s++) {
    const i = t[s].x, l = t[s].y, u = t[a].x, c = t[a].y;
    l > o != c > o && n < (u - i) * (o - l) / (c - l) + i && (r = !r);
  }
  return r;
}
function xm(e, t) {
  if (!t)
    return !1;
  const n = { x: e.clientX, y: e.clientY };
  return _m(n, t);
}
function To(e) {
  return e.pointerType === "mouse";
}
const Qr = "focusScope.autoFocusOnMount", es = "focusScope.autoFocusOnUnmount", $i = { bubbles: !1, cancelable: !0 };
function Cm(e, { select: t = !1 } = {}) {
  const n = ot();
  for (const o of e)
    if (dn(o, { select: t }), ot() !== n)
      return !0;
}
function Sm(e) {
  const t = $u(e), n = Ti(t, e), o = Ti(t.reverse(), e);
  return [n, o];
}
function $u(e) {
  const t = [], n = document.createTreeWalker(e, NodeFilter.SHOW_ELEMENT, {
    acceptNode: (o) => {
      const r = o.tagName === "INPUT" && o.type === "hidden";
      return o.disabled || o.hidden || r ? NodeFilter.FILTER_SKIP : o.tabIndex >= 0 ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
    }
  });
  for (; n.nextNode(); ) t.push(n.currentNode);
  return t;
}
function Ti(e, t) {
  for (const n of e)
    if (!$m(n, { upTo: t }))
      return n;
}
function $m(e, { upTo: t }) {
  if (getComputedStyle(e).visibility === "hidden")
    return !0;
  for (; e; ) {
    if (t !== void 0 && e === t)
      return !1;
    if (getComputedStyle(e).display === "none")
      return !0;
    e = e.parentElement;
  }
  return !1;
}
function Tm(e) {
  return e instanceof HTMLInputElement && "select" in e;
}
function dn(e, { select: t = !1 } = {}) {
  if (e && e.focus) {
    const n = ot();
    e.focus({ preventScroll: !0 }), e !== n && Tm(e) && t && e.select();
  }
}
const Pm = zh(() => B([]));
function Em() {
  const e = Pm();
  return {
    add(t) {
      const n = e.value[0];
      t !== n && (n == null || n.pause()), e.value = Pi(e.value, t), e.value.unshift(t);
    },
    remove(t) {
      var n;
      e.value = Pi(e.value, t), (n = e.value[0]) == null || n.resume();
    }
  };
}
function Pi(e, t) {
  const n = [...e], o = n.indexOf(t);
  return o !== -1 && n.splice(o, 1), n;
}
function Am(e) {
  return e.filter((t) => t.tagName !== "A");
}
const da = /* @__PURE__ */ T({
  __name: "FocusScope",
  props: {
    loop: { type: Boolean, default: !1 },
    trapped: { type: Boolean, default: !1 },
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["mountAutoFocus", "unmountAutoFocus"],
  setup(e, { emit: t }) {
    const n = e, o = t, { currentRef: r, currentElement: s } = X(), a = B(null), i = Em(), l = Po({
      paused: !1,
      pause() {
        this.paused = !0;
      },
      resume() {
        this.paused = !1;
      }
    });
    Me((c) => {
      if (!on)
        return;
      const f = s.value;
      if (!n.trapped)
        return;
      function p(C) {
        if (l.paused || !f)
          return;
        const v = C.target;
        f.contains(v) ? a.value = v : dn(a.value, { select: !0 });
      }
      function h(C) {
        if (l.paused || !f)
          return;
        const v = C.relatedTarget;
        v !== null && (f.contains(v) || dn(a.value, { select: !0 }));
      }
      function g(C) {
        f.contains(a.value) || dn(f);
      }
      document.addEventListener("focusin", p), document.addEventListener("focusout", h);
      const y = new MutationObserver(g);
      f && y.observe(f, { childList: !0, subtree: !0 }), c(() => {
        document.removeEventListener("focusin", p), document.removeEventListener("focusout", h), y.disconnect();
      });
    }), Me(async (c) => {
      const f = s.value;
      if (await Le(), !f)
        return;
      i.add(l);
      const p = ot();
      if (!f.contains(p)) {
        const g = new CustomEvent(Qr, $i);
        f.addEventListener(Qr, (y) => o("mountAutoFocus", y)), f.dispatchEvent(g), g.defaultPrevented || (Cm(Am($u(f)), {
          select: !0
        }), ot() === p && dn(f));
      }
      c(() => {
        f.removeEventListener(Qr, (C) => o("mountAutoFocus", C));
        const g = new CustomEvent(es, $i), y = (C) => {
          o("unmountAutoFocus", C);
        };
        f.addEventListener(es, y), f.dispatchEvent(g), setTimeout(() => {
          g.defaultPrevented || dn(p ?? document.body, { select: !0 }), f.removeEventListener(es, y), i.remove(l);
        }, 0);
      });
    });
    function u(c) {
      if (!n.loop && !n.trapped || l.paused)
        return;
      const f = c.key === "Tab" && !c.altKey && !c.ctrlKey && !c.metaKey, p = ot();
      if (f && p) {
        const h = c.currentTarget, [g, y] = Sm(h);
        g && y ? !c.shiftKey && p === y ? (c.preventDefault(), n.loop && dn(g, { select: !0 })) : c.shiftKey && p === g && (c.preventDefault(), n.loop && dn(y, { select: !0 })) : p === h && c.preventDefault();
      }
    }
    return (c, f) => (_(), E(d(ie), {
      ref_key: "currentRef",
      ref: r,
      tabindex: "-1",
      "as-child": c.asChild,
      as: c.as,
      onKeydown: u
    }, {
      default: S(() => [
        P(c.$slots, "default")
      ]),
      _: 3
    }, 8, ["as-child", "as"]));
  }
}), Tu = /* @__PURE__ */ T({
  __name: "DialogContentImpl",
  props: {
    forceMount: { type: Boolean },
    trapFocus: { type: Boolean },
    disableOutsidePointerEvents: { type: Boolean },
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "openAutoFocus", "closeAutoFocus"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = Wt(), { forwardRef: s, currentElement: a } = X();
    return r.titleId || (r.titleId = yt(void 0, "reka-dialog-title")), r.descriptionId || (r.descriptionId = yt(void 0, "reka-dialog-description")), Se(() => {
      r.contentElement = a, ot() !== document.body && (r.triggerElement.value = ot());
    }), (i, l) => (_(), E(d(da), {
      "as-child": "",
      loop: "",
      trapped: n.trapFocus,
      onMountAutoFocus: l[5] || (l[5] = (u) => o("openAutoFocus", u)),
      onUnmountAutoFocus: l[6] || (l[6] = (u) => o("closeAutoFocus", u))
    }, {
      default: S(() => [
        W(d(Mr), z({
          id: d(r).contentId,
          ref: d(s),
          as: i.as,
          "as-child": i.asChild,
          "disable-outside-pointer-events": i.disableOutsidePointerEvents,
          role: "dialog",
          "aria-describedby": d(r).descriptionId,
          "aria-labelledby": d(r).titleId,
          "data-state": d(ua)(d(r).open.value)
        }, i.$attrs, {
          onDismiss: l[0] || (l[0] = (u) => d(r).onOpenChange(!1)),
          onEscapeKeyDown: l[1] || (l[1] = (u) => o("escapeKeyDown", u)),
          onFocusOutside: l[2] || (l[2] = (u) => o("focusOutside", u)),
          onInteractOutside: l[3] || (l[3] = (u) => o("interactOutside", u)),
          onPointerDownOutside: l[4] || (l[4] = (u) => o("pointerDownOutside", u))
        }), {
          default: S(() => [
            P(i.$slots, "default")
          ]),
          _: 3
        }, 16, ["id", "as", "as-child", "disable-outside-pointer-events", "aria-describedby", "aria-labelledby", "data-state"])
      ]),
      _: 3
    }, 8, ["trapped"]));
  }
});
var Om = function(e) {
  if (typeof document > "u")
    return null;
  var t = Array.isArray(e) ? e[0] : e;
  return t.ownerDocument.body;
}, Ln = /* @__PURE__ */ new WeakMap(), zo = /* @__PURE__ */ new WeakMap(), jo = {}, ts = 0, Pu = function(e) {
  return e && (e.host || Pu(e.parentNode));
}, Bm = function(e, t) {
  return t.map(function(n) {
    if (e.contains(n))
      return n;
    var o = Pu(n);
    return o && e.contains(o) ? o : (console.error("aria-hidden", n, "in not contained inside", e, ". Doing nothing"), null);
  }).filter(function(n) {
    return !!n;
  });
}, km = function(e, t, n, o) {
  var r = Bm(t, Array.isArray(e) ? e : [e]);
  jo[n] || (jo[n] = /* @__PURE__ */ new WeakMap());
  var s = jo[n], a = [], i = /* @__PURE__ */ new Set(), l = new Set(r), u = function(f) {
    !f || i.has(f) || (i.add(f), u(f.parentNode));
  };
  r.forEach(u);
  var c = function(f) {
    !f || l.has(f) || Array.prototype.forEach.call(f.children, function(p) {
      if (i.has(p))
        c(p);
      else
        try {
          var h = p.getAttribute(o), g = h !== null && h !== "false", y = (Ln.get(p) || 0) + 1, C = (s.get(p) || 0) + 1;
          Ln.set(p, y), s.set(p, C), a.push(p), y === 1 && g && zo.set(p, !0), C === 1 && p.setAttribute(n, "true"), g || p.setAttribute(o, "true");
        } catch (v) {
          console.error("aria-hidden: cannot operate on ", p, v);
        }
    });
  };
  return c(t), i.clear(), ts++, function() {
    a.forEach(function(f) {
      var p = Ln.get(f) - 1, h = s.get(f) - 1;
      Ln.set(f, p), s.set(f, h), p || (zo.has(f) || f.removeAttribute(o), zo.delete(f)), h || f.removeAttribute(n);
    }), ts--, ts || (Ln = /* @__PURE__ */ new WeakMap(), Ln = /* @__PURE__ */ new WeakMap(), zo = /* @__PURE__ */ new WeakMap(), jo = {});
  };
}, Mm = function(e, t, n) {
  n === void 0 && (n = "data-aria-hidden");
  var o = Array.from(Array.isArray(e) ? e : [e]), r = Om(e);
  return r ? (o.push.apply(o, Array.from(r.querySelectorAll("[aria-live]"))), km(o, r, n, "aria-hidden")) : function() {
    return null;
  };
};
function fa(e) {
  let t;
  be(() => zt(e), (n) => {
    n ? t = Mm(n) : t && t();
  }), bt(() => {
    t && t();
  });
}
const Dm = /* @__PURE__ */ T({
  __name: "DialogContentModal",
  props: {
    forceMount: { type: Boolean },
    trapFocus: { type: Boolean },
    disableOutsidePointerEvents: { type: Boolean },
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "openAutoFocus", "closeAutoFocus"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = Wt(), s = An(o), { forwardRef: a, currentElement: i } = X();
    return fa(i), (l, u) => (_(), E(Tu, z({ ...n, ...d(s) }, {
      ref: d(a),
      "trap-focus": d(r).open.value,
      "disable-outside-pointer-events": !0,
      onCloseAutoFocus: u[0] || (u[0] = (c) => {
        var f;
        c.defaultPrevented || (c.preventDefault(), (f = d(r).triggerElement.value) == null || f.focus());
      }),
      onPointerDownOutside: u[1] || (u[1] = (c) => {
        const f = c.detail.originalEvent, p = f.button === 0 && f.ctrlKey === !0;
        (f.button === 2 || p) && c.preventDefault();
      }),
      onFocusOutside: u[2] || (u[2] = (c) => {
        c.preventDefault();
      })
    }), {
      default: S(() => [
        P(l.$slots, "default")
      ]),
      _: 3
    }, 16, ["trap-focus"]));
  }
}), Rm = /* @__PURE__ */ T({
  __name: "DialogContentNonModal",
  props: {
    forceMount: { type: Boolean },
    trapFocus: { type: Boolean },
    disableOutsidePointerEvents: { type: Boolean },
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "openAutoFocus", "closeAutoFocus"],
  setup(e, { emit: t }) {
    const n = e, r = An(t);
    X();
    const s = Wt(), a = B(!1), i = B(!1);
    return (l, u) => (_(), E(Tu, z({ ...n, ...d(r) }, {
      "trap-focus": !1,
      "disable-outside-pointer-events": !1,
      onCloseAutoFocus: u[0] || (u[0] = (c) => {
        var f;
        c.defaultPrevented || (a.value || (f = d(s).triggerElement.value) == null || f.focus(), c.preventDefault()), a.value = !1, i.value = !1;
      }),
      onInteractOutside: u[1] || (u[1] = (c) => {
        var h;
        c.defaultPrevented || (a.value = !0, c.detail.originalEvent.type === "pointerdown" && (i.value = !0));
        const f = c.target;
        ((h = d(s).triggerElement.value) == null ? void 0 : h.contains(f)) && c.preventDefault(), c.detail.originalEvent.type === "focusin" && i.value && c.preventDefault();
      })
    }), {
      default: S(() => [
        P(l.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Im = /* @__PURE__ */ T({
  __name: "DialogContent",
  props: {
    forceMount: { type: Boolean },
    trapFocus: { type: Boolean },
    disableOutsidePointerEvents: { type: Boolean },
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "openAutoFocus", "closeAutoFocus"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = Wt(), s = An(o), { forwardRef: a } = X();
    return (i, l) => (_(), E(d(kt), {
      present: i.forceMount || d(r).open.value
    }, {
      default: S(() => [
        d(r).modal.value ? (_(), E(Dm, z({
          key: 0,
          ref: d(a)
        }, { ...n, ...d(s), ...i.$attrs }), {
          default: S(() => [
            P(i.$slots, "default")
          ]),
          _: 3
        }, 16)) : (_(), E(Rm, z({
          key: 1,
          ref: d(a)
        }, { ...n, ...d(s), ...i.$attrs }), {
          default: S(() => [
            P(i.$slots, "default")
          ]),
          _: 3
        }, 16))
      ]),
      _: 3
    }, 8, ["present"]));
  }
});
function ns(e) {
  if (e === null || typeof e != "object")
    return !1;
  const t = Object.getPrototypeOf(e);
  return t !== null && t !== Object.prototype && Object.getPrototypeOf(t) !== null || Symbol.iterator in e ? !1 : Symbol.toStringTag in e ? Object.prototype.toString.call(e) === "[object Module]" : !0;
}
function Ps(e, t, n = ".", o) {
  if (!ns(t))
    return Ps(e, {}, n, o);
  const r = Object.assign({}, t);
  for (const s in e) {
    if (s === "__proto__" || s === "constructor")
      continue;
    const a = e[s];
    a != null && (o && o(r, s, a, n) || (Array.isArray(a) && Array.isArray(r[s]) ? r[s] = [...a, ...r[s]] : ns(a) && ns(r[s]) ? r[s] = Ps(
      a,
      r[s],
      (n ? `${n}.` : "") + s.toString(),
      o
    ) : r[s] = a));
  }
  return r;
}
function Lm(e) {
  return (...t) => (
    // eslint-disable-next-line unicorn/no-array-reduce
    t.reduce((n, o) => Ps(n, o, "", e), {})
  );
}
const Fm = Lm(), Vm = gu(() => {
  const e = B(/* @__PURE__ */ new Map()), t = B(), n = O(() => {
    for (const a of e.value.values())
      if (a)
        return !0;
    return !1;
  }), o = Br({
    scrollBody: B(!0)
  });
  let r = null;
  const s = () => {
    document.body.style.paddingRight = "", document.body.style.marginRight = "", document.body.style.pointerEvents = "", document.body.style.removeProperty("--scrollbar-width"), document.body.style.overflow = t.value ?? "", xi && (r == null || r()), t.value = void 0;
  };
  return be(n, (a, i) => {
    var f;
    if (!on)
      return;
    if (!a) {
      i && s();
      return;
    }
    t.value === void 0 && (t.value = document.body.style.overflow);
    const l = window.innerWidth - document.documentElement.clientWidth, u = { padding: l, margin: 0 }, c = (f = o.scrollBody) != null && f.value ? typeof o.scrollBody.value == "object" ? Fm({
      padding: o.scrollBody.value.padding === !0 ? l : o.scrollBody.value.padding,
      margin: o.scrollBody.value.margin === !0 ? l : o.scrollBody.value.margin
    }, u) : u : { padding: 0, margin: 0 };
    l > 0 && (document.body.style.paddingRight = typeof c.padding == "number" ? `${c.padding}px` : String(c.padding), document.body.style.marginRight = typeof c.margin == "number" ? `${c.margin}px` : String(c.margin), document.body.style.setProperty("--scrollbar-width", `${l}px`), document.body.style.overflow = "hidden"), xi && (r = Xn(
      document,
      "touchmove",
      (p) => Nm(p),
      { passive: !1 }
    )), Le(() => {
      document.body.style.pointerEvents = "none", document.body.style.overflow = "hidden";
    });
  }, { immediate: !0, flush: "sync" }), e;
});
function pa(e) {
  const t = Math.random().toString(36).substring(2, 7), n = Vm();
  n.value.set(t, e ?? !1);
  const o = O({
    get: () => n.value.get(t) ?? !1,
    set: (r) => n.value.set(t, r)
  });
  return Xh(() => {
    n.value.delete(t);
  }), o;
}
function Eu(e) {
  const t = window.getComputedStyle(e);
  if (t.overflowX === "scroll" || t.overflowY === "scroll" || t.overflowX === "auto" && e.clientWidth < e.scrollWidth || t.overflowY === "auto" && e.clientHeight < e.scrollHeight)
    return !0;
  {
    const n = e.parentNode;
    return !(n instanceof Element) || n.tagName === "BODY" ? !1 : Eu(n);
  }
}
function Nm(e) {
  const t = e || window.event, n = t.target;
  return n instanceof Element && Eu(n) ? !1 : t.touches.length > 1 ? !0 : (t.preventDefault && t.cancelable && t.preventDefault(), !1);
}
const zm = /* @__PURE__ */ T({
  __name: "DialogOverlayImpl",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = Wt();
    return pa(!0), X(), (n, o) => (_(), E(d(ie), {
      as: n.as,
      "as-child": n.asChild,
      "data-state": d(t).open.value ? "open" : "closed",
      style: { "pointer-events": "auto" }
    }, {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 8, ["as", "as-child", "data-state"]));
  }
}), jm = /* @__PURE__ */ T({
  __name: "DialogOverlay",
  props: {
    forceMount: { type: Boolean },
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = Wt(), { forwardRef: n } = X();
    return (o, r) => {
      var s;
      return (s = d(t)) != null && s.modal.value ? (_(), E(d(kt), {
        key: 0,
        present: o.forceMount || d(t).open.value
      }, {
        default: S(() => [
          W(zm, z(o.$attrs, {
            ref: d(n),
            as: o.as,
            "as-child": o.asChild
          }), {
            default: S(() => [
              P(o.$slots, "default")
            ]),
            _: 3
          }, 16, ["as", "as-child"])
        ]),
        _: 3
      }, 8, ["present"])) : Be("", !0);
    };
  }
}), Au = /* @__PURE__ */ T({
  __name: "DialogClose",
  props: {
    asChild: { type: Boolean },
    as: { default: "button" }
  },
  setup(e) {
    const t = e;
    X();
    const n = Wt();
    return (o, r) => (_(), E(d(ie), z(t, {
      type: o.as === "button" ? "button" : void 0,
      onClick: r[0] || (r[0] = (s) => d(n).onOpenChange(!1))
    }), {
      default: S(() => [
        P(o.$slots, "default")
      ]),
      _: 3
    }, 16, ["type"]));
  }
}), Hm = /* @__PURE__ */ T({
  __name: "DialogTitle",
  props: {
    asChild: { type: Boolean },
    as: { default: "h2" }
  },
  setup(e) {
    const t = e, n = Wt();
    return X(), (o, r) => (_(), E(d(ie), z(t, {
      id: d(n).titleId
    }), {
      default: S(() => [
        P(o.$slots, "default")
      ]),
      _: 3
    }, 16, ["id"]));
  }
}), Wm = /* @__PURE__ */ T({
  __name: "DialogDescription",
  props: {
    asChild: { type: Boolean },
    as: { default: "p" }
  },
  setup(e) {
    const t = e;
    X();
    const n = Wt();
    return (o, r) => (_(), E(d(ie), z(t, {
      id: d(n).descriptionId
    }), {
      default: S(() => [
        P(o.$slots, "default")
      ]),
      _: 3
    }, 16, ["id"]));
  }
});
function Es() {
  const e = B(), t = O(() => {
    var n, o;
    return ["#text", "#comment"].includes((n = e.value) == null ? void 0 : n.$el.nodeName) ? (o = e.value) == null ? void 0 : o.$el.nextElementSibling : zt(e);
  });
  return {
    primitiveElement: e,
    currentElement: t
  };
}
const Km = "rovingFocusGroup.onEntryFocus", Um = { bubbles: !1, cancelable: !0 }, Gm = {
  ArrowLeft: "prev",
  ArrowUp: "prev",
  ArrowRight: "next",
  ArrowDown: "next",
  PageUp: "first",
  Home: "first",
  PageDown: "last",
  End: "last"
};
function Ym(e, t) {
  return t !== "rtl" ? e : e === "ArrowLeft" ? "ArrowRight" : e === "ArrowRight" ? "ArrowLeft" : e;
}
function qm(e, t, n) {
  const o = Ym(e.key, n);
  if (!(t === "vertical" && ["ArrowLeft", "ArrowRight"].includes(o)) && !(t === "horizontal" && ["ArrowUp", "ArrowDown"].includes(o)))
    return Gm[o];
}
function Ou(e, t = !1) {
  const n = ot();
  for (const o of e)
    if (o === n || (o.focus({ preventScroll: t }), ot() !== n))
      return;
}
function Xm(e, t) {
  return e.map((n, o) => e[(t + o) % e.length]);
}
const Ei = "data-reka-collection-item";
function rn(e = {}) {
  const { key: t = "", isProvider: n = !1 } = e, o = `${t}CollectionProvider`;
  let r;
  if (n) {
    const c = B(/* @__PURE__ */ new Map());
    r = {
      collectionRef: B(),
      itemMap: c
    }, Jn(o, r);
  } else
    r = Zt(o);
  const s = (c = !1) => {
    const f = r.collectionRef.value;
    if (!f)
      return [];
    const p = Array.from(f.querySelectorAll(`[${Ei}]`)), g = Array.from(r.itemMap.value.values()).sort(
      (y, C) => p.indexOf(y.ref) - p.indexOf(C.ref)
    );
    return c ? g : g.filter((y) => y.ref.dataset.disabled !== "");
  }, a = /* @__PURE__ */ T({
    name: "CollectionSlot",
    setup(c, { slots: f }) {
      const { primitiveElement: p, currentElement: h } = Es();
      return be(h, () => {
        r.collectionRef.value = h.value;
      }), () => St(Ss, { ref: p }, f);
    }
  }), i = /* @__PURE__ */ T({
    name: "CollectionItem",
    inheritAttrs: !1,
    props: {
      value: {
        // It accepts any value
        validator: () => !0
      }
    },
    setup(c, { slots: f, attrs: p }) {
      const { primitiveElement: h, currentElement: g } = Es();
      return Me((y) => {
        if (g.value) {
          const C = ul(g.value);
          r.itemMap.value.set(C, { ref: g.value, value: c.value }), y(() => r.itemMap.value.delete(C));
        }
      }), () => St(Ss, { ...p, [Ei]: "", ref: h }, f);
    }
  }), l = O(() => Array.from(r.itemMap.value.values())), u = O(() => r.itemMap.value.size);
  return { getItems: s, reactiveItems: l, itemMapSize: u, CollectionSlot: a, CollectionItem: i };
}
const [Jm, Zm] = Ve("RovingFocusGroup"), Bu = /* @__PURE__ */ T({
  __name: "RovingFocusGroup",
  props: {
    orientation: { default: void 0 },
    dir: {},
    loop: { type: Boolean, default: !1 },
    currentTabStopId: {},
    defaultCurrentTabStopId: {},
    preventScrollOnEntryFocus: { type: Boolean, default: !1 },
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["entryFocus", "update:currentTabStopId"],
  setup(e, { expose: t, emit: n }) {
    const o = e, r = n, { loop: s, orientation: a, dir: i } = Xe(o), l = to(i), u = vt(o, "currentTabStopId", r, {
      defaultValue: o.defaultCurrentTabStopId,
      passive: o.currentTabStopId === void 0
    }), c = B(!1), f = B(!1), p = B(0), { getItems: h, CollectionSlot: g } = rn({ isProvider: !0 });
    function y(v) {
      const w = !f.value;
      if (v.currentTarget && v.target === v.currentTarget && w && !c.value) {
        const m = new CustomEvent(Km, Um);
        if (v.currentTarget.dispatchEvent(m), r("entryFocus", m), !m.defaultPrevented) {
          const x = h().map((H) => H.ref).filter((H) => H.dataset.disabled !== ""), A = x.find((H) => H.getAttribute("data-active") === "true"), R = x.find(
            (H) => H.id === u.value
          ), I = [A, R, ...x].filter(
            Boolean
          );
          Ou(I, o.preventScrollOnEntryFocus);
        }
      }
      f.value = !1;
    }
    function C() {
      setTimeout(() => {
        f.value = !1;
      }, 1);
    }
    return t({
      getItems: h
    }), Zm({
      loop: s,
      dir: l,
      orientation: a,
      currentTabStopId: u,
      onItemFocus: (v) => {
        u.value = v;
      },
      onItemShiftTab: () => {
        c.value = !0;
      },
      onFocusableItemAdd: () => {
        p.value++;
      },
      onFocusableItemRemove: () => {
        p.value--;
      }
    }), (v, w) => (_(), E(d(g), null, {
      default: S(() => [
        W(d(ie), {
          tabindex: c.value || p.value === 0 ? -1 : 0,
          "data-orientation": d(a),
          as: v.as,
          "as-child": v.asChild,
          dir: d(l),
          style: { outline: "none" },
          onMousedown: w[0] || (w[0] = (m) => f.value = !0),
          onMouseup: C,
          onFocus: y,
          onBlur: w[1] || (w[1] = (m) => c.value = !1)
        }, {
          default: S(() => [
            P(v.$slots, "default")
          ]),
          _: 3
        }, 8, ["tabindex", "data-orientation", "as", "as-child", "dir"])
      ]),
      _: 3
    }));
  }
});
function ku(e) {
  return O(() => {
    var t;
    return qh(e) ? !!((t = zt(e)) != null && t.closest("form")) : !0;
  });
}
const Qm = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "VisuallyHiddenInputBubble",
  props: {
    name: {},
    value: {},
    checked: { type: Boolean, default: void 0 },
    required: { type: Boolean },
    disabled: { type: Boolean },
    feature: { default: "fully-hidden" }
  },
  setup(e) {
    const t = e, { primitiveElement: n, currentElement: o } = Es(), r = O(() => t.checked ?? t.value);
    return be(r, (s, a) => {
      if (!o.value)
        return;
      const i = o.value, l = window.HTMLInputElement.prototype, c = Object.getOwnPropertyDescriptor(l, "value").set;
      if (c && s !== a) {
        const f = new Event("input", { bubbles: !0 }), p = new Event("change", { bubbles: !0 });
        c.call(i, s), i.dispatchEvent(f), i.dispatchEvent(p);
      }
    }), (s, a) => (_(), E(ia, z({
      ref_key: "primitiveElement",
      ref: n
    }, { ...t, ...s.$attrs }, { as: "input" }), null, 16));
  }
}), eg = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "VisuallyHiddenInput",
  props: {
    name: {},
    value: {},
    checked: { type: Boolean, default: void 0 },
    required: { type: Boolean },
    disabled: { type: Boolean },
    feature: { default: "fully-hidden" }
  },
  setup(e) {
    const t = e, n = O(() => typeof t.value == "string" || typeof t.value == "number" || typeof t.value == "boolean" ? [{ name: t.name, value: t.value }] : typeof t.value == "object" && Array.isArray(t.value) ? t.value.flatMap((o, r) => typeof o == "object" ? Object.entries(o).map(([s, a]) => ({ name: `[${t.name}][${r}][${s}]`, value: a })) : { name: `[${t.name}][${r}]`, value: o }) : t.value !== null && typeof t.value == "object" && !Array.isArray(t.value) ? Object.entries(t.value).map(([o, r]) => ({ name: `[${t.name}][${o}]`, value: r })) : []);
    return (o, r) => (_(!0), oe(ke, null, wo(n.value, (s) => (_(), E(Qm, z({
      key: s.name,
      ref_for: !0
    }, { ...t, ...o.$attrs }, {
      name: s.name,
      value: s.value
    }), null, 16, ["name", "value"]))), 128));
  }
}), tg = /* @__PURE__ */ T({
  __name: "RovingFocusItem",
  props: {
    tabStopId: {},
    focusable: { type: Boolean, default: !0 },
    active: { type: Boolean, default: !0 },
    allowShiftKey: { type: Boolean },
    asChild: { type: Boolean },
    as: { default: "span" }
  },
  setup(e) {
    const t = e, n = Jm(), o = yt(), r = O(() => t.tabStopId || o), s = O(
      () => n.currentTabStopId.value === r.value
    ), { getItems: a, CollectionItem: i } = rn();
    Se(() => {
      t.focusable && n.onFocusableItemAdd();
    }), bt(() => {
      t.focusable && n.onFocusableItemRemove();
    });
    function l(u) {
      if (u.key === "Tab" && u.shiftKey) {
        n.onItemShiftTab();
        return;
      }
      if (u.target !== u.currentTarget)
        return;
      const c = qm(
        u,
        n.orientation.value,
        n.dir.value
      );
      if (c !== void 0) {
        if (u.metaKey || u.ctrlKey || u.altKey || !t.allowShiftKey && u.shiftKey)
          return;
        u.preventDefault();
        let f = [...a().map((p) => p.ref).filter((p) => p.dataset.disabled !== "")];
        if (c === "last")
          f.reverse();
        else if (c === "prev" || c === "next") {
          c === "prev" && f.reverse();
          const p = f.indexOf(
            u.currentTarget
          );
          f = n.loop.value ? Xm(f, p + 1) : f.slice(p + 1);
        }
        Le(() => Ou(f));
      }
    }
    return (u, c) => (_(), E(d(i), null, {
      default: S(() => [
        W(d(ie), {
          tabindex: s.value ? 0 : -1,
          "data-orientation": d(n).orientation.value,
          "data-active": u.active,
          "data-disabled": u.focusable ? void 0 : "",
          as: u.as,
          "as-child": u.asChild,
          onMousedown: c[0] || (c[0] = (f) => {
            u.focusable ? d(n).onItemFocus(r.value) : f.preventDefault();
          }),
          onFocus: c[1] || (c[1] = (f) => d(n).onItemFocus(r.value)),
          onKeydown: l
        }, {
          default: S(() => [
            P(u.$slots, "default")
          ]),
          _: 3
        }, 8, ["tabindex", "data-orientation", "data-active", "data-disabled", "as", "as-child"])
      ]),
      _: 3
    }));
  }
}), [Mu, ng] = Ve("PopperRoot"), Dr = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "PopperRoot",
  setup(e) {
    const t = B();
    return ng({
      anchor: t,
      onAnchorChange: (n) => t.value = n
    }), (n, o) => P(n.$slots, "default");
  }
});
function ha(e) {
  const t = vu("", 1e3);
  return {
    search: t,
    handleTypeaheadSearch: (r, s) => {
      t.value = t.value + r;
      {
        const a = ot(), i = s.map((p) => {
          var h, g;
          return {
            ...p,
            textValue: ((h = p.value) == null ? void 0 : h.textValue) ?? ((g = p.ref.textContent) == null ? void 0 : g.trim()) ?? ""
          };
        }), l = i.find((p) => p.ref === a), u = i.map((p) => p.textValue), c = rg(u, t.value, l == null ? void 0 : l.textValue), f = i.find((p) => p.textValue === c);
        return f && f.ref.focus(), f == null ? void 0 : f.ref;
      }
    },
    resetTypeahead: () => {
      t.value = "";
    }
  };
}
function og(e, t) {
  return e.map((n, o) => e[(t + o) % e.length]);
}
function rg(e, t, n) {
  const r = t.length > 1 && Array.from(t).every((u) => u === t[0]) ? t[0] : t, s = n ? e.indexOf(n) : -1;
  let a = og(e, Math.max(s, 0));
  r.length === 1 && (a = a.filter((u) => u !== n));
  const l = a.find(
    (u) => u.toLowerCase().startsWith(r.toLowerCase())
  );
  return l !== n ? l : void 0;
}
const ma = /* @__PURE__ */ T({
  __name: "PopperAnchor",
  props: {
    reference: {},
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e, { forwardRef: n, currentElement: o } = X(), r = Mu();
    return jl(() => {
      r.onAnchorChange(t.reference ?? o.value);
    }), (s, a) => (_(), E(d(ie), {
      ref: d(n),
      as: s.as,
      "as-child": s.asChild
    }, {
      default: S(() => [
        P(s.$slots, "default")
      ]),
      _: 3
    }, 8, ["as", "as-child"]));
  }
});
function sg(e) {
  return e !== null;
}
function ag(e) {
  return {
    name: "transformOrigin",
    options: e,
    fn(t) {
      var C, v, w;
      const { placement: n, rects: o, middlewareData: r } = t, a = ((C = r.arrow) == null ? void 0 : C.centerOffset) !== 0, i = a ? 0 : e.arrowWidth, l = a ? 0 : e.arrowHeight, [u, c] = As(n), f = { start: "0%", center: "50%", end: "100%" }[c], p = (((v = r.arrow) == null ? void 0 : v.x) ?? 0) + i / 2, h = (((w = r.arrow) == null ? void 0 : w.y) ?? 0) + l / 2;
      let g = "", y = "";
      return u === "bottom" ? (g = a ? f : `${p}px`, y = `${-l}px`) : u === "top" ? (g = a ? f : `${p}px`, y = `${o.floating.height + l}px`) : u === "right" ? (g = `${-l}px`, y = a ? f : `${h}px`) : u === "left" && (g = `${o.floating.width + l}px`, y = a ? f : `${h}px`), { data: { x: g, y } };
    }
  };
}
function As(e) {
  const [t, n = "center"] = e.split("-");
  return [t, n];
}
function ig(e) {
  const t = B(), n = O(() => {
    var r;
    return ((r = t.value) == null ? void 0 : r.width) ?? 0;
  }), o = O(() => {
    var r;
    return ((r = t.value) == null ? void 0 : r.height) ?? 0;
  });
  return Se(() => {
    const r = zt(e);
    if (r) {
      t.value = { width: r.offsetWidth, height: r.offsetHeight };
      const s = new ResizeObserver((a) => {
        if (!Array.isArray(a) || !a.length)
          return;
        const i = a[0];
        let l, u;
        if ("borderBoxSize" in i) {
          const c = i.borderBoxSize, f = Array.isArray(c) ? c[0] : c;
          l = f.inlineSize, u = f.blockSize;
        } else
          l = r.offsetWidth, u = r.offsetHeight;
        t.value = { width: l, height: u };
      });
      return s.observe(r, { box: "border-box" }), () => s.unobserve(r);
    } else
      t.value = void 0;
  }), {
    width: n,
    height: o
  };
}
const Du = {
  side: "bottom",
  sideOffset: 0,
  align: "center",
  alignOffset: 0,
  arrowPadding: 0,
  avoidCollisions: !0,
  collisionBoundary: () => [],
  collisionPadding: 0,
  sticky: "partial",
  hideWhenDetached: !1,
  positionStrategy: "fixed",
  updatePositionStrategy: "optimized",
  prioritizePosition: !1
}, [L0, lg] = Ve("PopperContent"), ga = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "PopperContent",
  props: /* @__PURE__ */ El({
    side: {},
    sideOffset: {},
    align: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    disableUpdateOnLayoutShift: { type: Boolean },
    prioritizePosition: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: {}
  }, {
    ...Du
  }),
  emits: ["placed"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = Mu(), { forwardRef: s, currentElement: a } = X(), i = B(), l = B(), { width: u, height: c } = ig(l), f = O(
      () => n.side + (n.align !== "center" ? `-${n.align}` : "")
    ), p = O(() => typeof n.collisionPadding == "number" ? n.collisionPadding : { top: 0, right: 0, bottom: 0, left: 0, ...n.collisionPadding }), h = O(() => Array.isArray(n.collisionBoundary) ? n.collisionBoundary : [n.collisionBoundary]), g = O(() => ({
      padding: p.value,
      boundary: h.value.filter(sg),
      // with `strategy: 'fixed'`, this is the only way to get it to respect boundaries
      altBoundary: h.value.length > 0
    })), y = Vh(() => [
      Ah({
        mainAxis: n.sideOffset + c.value,
        alignmentAxis: n.alignOffset
      }),
      n.prioritizePosition && n.avoidCollisions && bi({
        ...g.value
      }),
      n.avoidCollisions && Oh({
        mainAxis: !0,
        crossAxis: !!n.prioritizePosition,
        limiter: n.sticky === "partial" ? Dh() : void 0,
        ...g.value
      }),
      !n.prioritizePosition && n.avoidCollisions && bi({
        ...g.value
      }),
      Bh({
        ...g.value,
        apply: ({ elements: K, rects: J, availableWidth: ge, availableHeight: ce }) => {
          const { width: Pe, height: ue } = J.reference, ee = K.floating.style;
          ee.setProperty(
            "--reka-popper-available-width",
            `${ge}px`
          ), ee.setProperty(
            "--reka-popper-available-height",
            `${ce}px`
          ), ee.setProperty(
            "--reka-popper-anchor-width",
            `${Pe}px`
          ), ee.setProperty(
            "--reka-popper-anchor-height",
            `${ue}px`
          );
        }
      }),
      l.value && Lh({ element: l.value, padding: n.arrowPadding }),
      ag({
        arrowWidth: u.value,
        arrowHeight: c.value
      }),
      n.hideWhenDetached && kh({ strategy: "referenceHidden", ...g.value })
    ]), C = O(() => n.reference ?? r.anchor.value), { floatingStyles: v, placement: w, isPositioned: m, middlewareData: x } = Fh(
      C,
      i,
      {
        strategy: n.positionStrategy,
        placement: f,
        whileElementsMounted: (...K) => Eh(...K, {
          layoutShift: !n.disableUpdateOnLayoutShift,
          animationFrame: n.updatePositionStrategy === "always"
        }),
        middleware: y
      }
    ), A = O(
      () => As(w.value)[0]
    ), R = O(
      () => As(w.value)[1]
    );
    jl(() => {
      m.value && o("placed");
    });
    const I = O(
      () => {
        var K;
        return ((K = x.value.arrow) == null ? void 0 : K.centerOffset) !== 0;
      }
    ), H = B("");
    Me(() => {
      a.value && (H.value = window.getComputedStyle(a.value).zIndex);
    });
    const k = O(() => {
      var K;
      return ((K = x.value.arrow) == null ? void 0 : K.x) ?? 0;
    }), j = O(() => {
      var K;
      return ((K = x.value.arrow) == null ? void 0 : K.y) ?? 0;
    });
    return lg({
      placedSide: A,
      onArrowChange: (K) => l.value = K,
      arrowX: k,
      arrowY: j,
      shouldHideArrow: I
    }), (K, J) => {
      var ge, ce, Pe;
      return _(), oe("div", {
        ref_key: "floatingRef",
        ref: i,
        "data-reka-popper-content-wrapper": "",
        style: nt({
          ...d(v),
          transform: d(m) ? d(v).transform : "translate(0, -200%)",
          // keep off the page when measuring
          minWidth: "max-content",
          zIndex: H.value,
          "--reka-popper-transform-origin": [
            (ge = d(x).transformOrigin) == null ? void 0 : ge.x,
            (ce = d(x).transformOrigin) == null ? void 0 : ce.y
          ].join(" "),
          // hide the content if using the hide middleware and should be hidden
          // set visibility to hidden and disable pointer events so the UI behaves
          // as if the PopperContent isn't there at all
          ...((Pe = d(x).hide) == null ? void 0 : Pe.referenceHidden) && {
            visibility: "hidden",
            pointerEvents: "none"
          }
        })
      }, [
        W(d(ie), z({ ref: d(s) }, K.$attrs, {
          "as-child": n.asChild,
          as: K.as,
          "data-side": A.value,
          "data-align": R.value,
          style: {
            // if the PopperContent hasn't been placed yet (not all measurements done)
            // we prevent animations so that users's animation don't kick in too early referring wrong sides
            animation: d(m) ? void 0 : "none"
          }
        }), {
          default: S(() => [
            P(K.$slots, "default")
          ]),
          _: 3
        }, 16, ["as-child", "as", "data-side", "data-align", "style"])
      ], 4);
    };
  }
});
function Ru(e) {
  const t = Br({
    nonce: B()
  });
  return O(() => {
    var n;
    return (e == null ? void 0 : e.value) || ((n = t.nonce) == null ? void 0 : n.value);
  });
}
function ug() {
  const e = B(!1);
  return Se(() => {
    Xn("keydown", () => {
      e.value = !0;
    }, { capture: !0, passive: !0 }), Xn(["pointerdown", "pointermove"], () => {
      e.value = !1;
    }, { capture: !0, passive: !0 });
  }), e;
}
const cg = gu(ug), [On, Iu] = Ve(["MenuRoot", "MenuSub"], "MenuContext"), [Mo, dg] = Ve("MenuRoot"), fg = /* @__PURE__ */ T({
  __name: "MenuRoot",
  props: {
    open: { type: Boolean, default: !1 },
    dir: {},
    modal: { type: Boolean, default: !0 }
  },
  emits: ["update:open"],
  setup(e, { emit: t }) {
    const n = e, o = t, { modal: r, dir: s } = Xe(n), a = to(s), i = vt(n, "open", o), l = B(), u = cg();
    return Iu({
      open: i,
      onOpenChange: (c) => {
        i.value = c;
      },
      content: l,
      onContentChange: (c) => {
        l.value = c;
      }
    }), dg({
      onClose: () => {
        i.value = !1;
      },
      isUsingKeyboardRef: u,
      dir: a,
      modal: r
    }), (c, f) => (_(), E(d(Dr), null, {
      default: S(() => [
        P(c.$slots, "default")
      ]),
      _: 3
    }));
  }
}), Lu = /* @__PURE__ */ T({
  __name: "MenuAnchor",
  props: {
    reference: {},
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(ma), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), pg = /* @__PURE__ */ T({
  __name: "MenuPortal",
  props: {
    to: {},
    disabled: { type: Boolean },
    defer: { type: Boolean },
    forceMount: { type: Boolean }
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(kr), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
});
let os = 0;
function Fu() {
  Me((e) => {
    if (!on)
      return;
    const t = document.querySelectorAll("[data-reka-focus-guard]");
    document.body.insertAdjacentElement(
      "afterbegin",
      t[0] ?? Ai()
    ), document.body.insertAdjacentElement(
      "beforeend",
      t[1] ?? Ai()
    ), os++, e(() => {
      os === 1 && document.querySelectorAll("[data-reka-focus-guard]").forEach((n) => n.remove()), os--;
    });
  });
}
function Ai() {
  const e = document.createElement("span");
  return e.setAttribute("data-reka-focus-guard", ""), e.tabIndex = 0, e.style.outline = "none", e.style.opacity = "0", e.style.position = "fixed", e.style.pointerEvents = "none", e;
}
const [va, hg] = Ve("MenuContent"), ya = /* @__PURE__ */ T({
  __name: "MenuContentImpl",
  props: /* @__PURE__ */ El({
    loop: { type: Boolean },
    disableOutsidePointerEvents: { type: Boolean },
    disableOutsideScroll: { type: Boolean },
    trapFocus: { type: Boolean },
    side: {},
    sideOffset: {},
    align: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    disableUpdateOnLayoutShift: { type: Boolean },
    prioritizePosition: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: {}
  }, {
    ...Du
  }),
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "entryFocus", "openAutoFocus", "closeAutoFocus", "dismiss"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = On(), s = Mo(), { trapFocus: a, disableOutsidePointerEvents: i, loop: l } = Xe(n);
    Fu(), pa(i.value);
    const u = B(""), c = B(0), f = B(0), p = B(null), h = B("right"), g = B(0), y = B(null), C = B(), { forwardRef: v, currentElement: w } = X(), { handleTypeaheadSearch: m } = ha();
    be(w, (k) => {
      r.onContentChange(k);
    }), bt(() => {
      window.clearTimeout(c.value);
    });
    function x(k) {
      var K, J;
      return h.value === ((K = p.value) == null ? void 0 : K.side) && xm(k, (J = p.value) == null ? void 0 : J.area);
    }
    async function A(k) {
      var j;
      o("openAutoFocus", k), !k.defaultPrevented && (k.preventDefault(), (j = w.value) == null || j.focus({
        preventScroll: !0
      }));
    }
    function R(k) {
      var ee;
      if (k.defaultPrevented)
        return;
      const K = k.target.closest("[data-reka-menu-content]") === k.currentTarget, J = k.ctrlKey || k.altKey || k.metaKey, ge = k.key.length === 1, ce = am(
        k,
        ot(),
        w.value,
        {
          loop: l.value,
          arrowKeyOptions: "vertical",
          dir: s == null ? void 0 : s.dir.value,
          focus: !0,
          attributeName: "[data-reka-collection-item]:not([data-disabled])"
        }
      );
      if (ce)
        return ce == null ? void 0 : ce.focus();
      if (k.code === "Space")
        return;
      const Pe = ((ee = C.value) == null ? void 0 : ee.getItems()) ?? [];
      if (K && (k.key === "Tab" && k.preventDefault(), !J && ge && m(k.key, Pe)), k.target !== w.value || !ym.includes(k.key))
        return;
      k.preventDefault();
      const ue = [...Pe.map((ne) => ne.ref)];
      Su.includes(k.key) && ue.reverse(), Ts(ue);
    }
    function I(k) {
      var j, K;
      (K = (j = k == null ? void 0 : k.currentTarget) == null ? void 0 : j.contains) != null && K.call(j, k.target) || (window.clearTimeout(c.value), u.value = "");
    }
    function H(k) {
      var J;
      if (!To(k))
        return;
      const j = k.target, K = g.value !== k.clientX;
      if ((J = k == null ? void 0 : k.currentTarget) != null && J.contains(j) && K) {
        const ge = k.clientX > g.value ? "right" : "left";
        h.value = ge, g.value = k.clientX;
      }
    }
    return hg({
      onItemEnter: (k) => !!x(k),
      onItemLeave: (k) => {
        var j;
        x(k) || ((j = w.value) == null || j.focus(), y.value = null);
      },
      onTriggerLeave: (k) => !!x(k),
      searchRef: u,
      pointerGraceTimerRef: f,
      onPointerGraceIntentChange: (k) => {
        p.value = k;
      }
    }), (k, j) => (_(), E(d(da), {
      "as-child": "",
      trapped: d(a),
      onMountAutoFocus: A,
      onUnmountAutoFocus: j[7] || (j[7] = (K) => o("closeAutoFocus", K))
    }, {
      default: S(() => [
        W(d(Mr), {
          "as-child": "",
          "disable-outside-pointer-events": d(i),
          onEscapeKeyDown: j[2] || (j[2] = (K) => o("escapeKeyDown", K)),
          onPointerDownOutside: j[3] || (j[3] = (K) => o("pointerDownOutside", K)),
          onFocusOutside: j[4] || (j[4] = (K) => o("focusOutside", K)),
          onInteractOutside: j[5] || (j[5] = (K) => o("interactOutside", K)),
          onDismiss: j[6] || (j[6] = (K) => o("dismiss"))
        }, {
          default: S(() => [
            W(d(Bu), {
              ref_key: "rovingFocusGroupRef",
              ref: C,
              "current-tab-stop-id": y.value,
              "onUpdate:currentTabStopId": j[0] || (j[0] = (K) => y.value = K),
              "as-child": "",
              orientation: "vertical",
              dir: d(s).dir.value,
              loop: d(l),
              onEntryFocus: j[1] || (j[1] = (K) => {
                o("entryFocus", K), d(s).isUsingKeyboardRef.value || K.preventDefault();
              })
            }, {
              default: S(() => [
                W(d(ga), {
                  ref: d(v),
                  role: "menu",
                  as: k.as,
                  "as-child": k.asChild,
                  "aria-orientation": "vertical",
                  "data-reka-menu-content": "",
                  "data-state": d(ua)(d(r).open.value),
                  dir: d(s).dir.value,
                  side: k.side,
                  "side-offset": k.sideOffset,
                  align: k.align,
                  "align-offset": k.alignOffset,
                  "avoid-collisions": k.avoidCollisions,
                  "collision-boundary": k.collisionBoundary,
                  "collision-padding": k.collisionPadding,
                  "arrow-padding": k.arrowPadding,
                  "prioritize-position": k.prioritizePosition,
                  "position-strategy": k.positionStrategy,
                  "update-position-strategy": k.updatePositionStrategy,
                  sticky: k.sticky,
                  "hide-when-detached": k.hideWhenDetached,
                  reference: k.reference,
                  onKeydown: R,
                  onBlur: I,
                  onPointermove: H
                }, {
                  default: S(() => [
                    P(k.$slots, "default")
                  ]),
                  _: 3
                }, 8, ["as", "as-child", "data-state", "dir", "side", "side-offset", "align", "align-offset", "avoid-collisions", "collision-boundary", "collision-padding", "arrow-padding", "prioritize-position", "position-strategy", "update-position-strategy", "sticky", "hide-when-detached", "reference"])
              ]),
              _: 3
            }, 8, ["current-tab-stop-id", "dir", "loop"])
          ]),
          _: 3
        }, 8, ["disable-outside-pointer-events"])
      ]),
      _: 3
    }, 8, ["trapped"]));
  }
}), mg = /* @__PURE__ */ T({
  __name: "MenuRootContentModal",
  props: {
    loop: { type: Boolean },
    side: {},
    sideOffset: {},
    align: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    disableUpdateOnLayoutShift: { type: Boolean },
    prioritizePosition: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "entryFocus", "openAutoFocus", "closeAutoFocus"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = Ne(n, o), s = On(), { forwardRef: a, currentElement: i } = X();
    return fa(i), (l, u) => (_(), E(ya, z(d(r), {
      ref: d(a),
      "trap-focus": d(s).open.value,
      "disable-outside-pointer-events": d(s).open.value,
      "disable-outside-scroll": !0,
      onDismiss: u[0] || (u[0] = (c) => d(s).onOpenChange(!1)),
      onFocusOutside: u[1] || (u[1] = Et((c) => o("focusOutside", c), ["prevent"]))
    }), {
      default: S(() => [
        P(l.$slots, "default")
      ]),
      _: 3
    }, 16, ["trap-focus", "disable-outside-pointer-events"]));
  }
}), gg = /* @__PURE__ */ T({
  __name: "MenuRootContentNonModal",
  props: {
    loop: { type: Boolean },
    side: {},
    sideOffset: {},
    align: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    disableUpdateOnLayoutShift: { type: Boolean },
    prioritizePosition: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "entryFocus", "openAutoFocus", "closeAutoFocus"],
  setup(e, { emit: t }) {
    const r = Ne(e, t), s = On();
    return (a, i) => (_(), E(ya, z(d(r), {
      "trap-focus": !1,
      "disable-outside-pointer-events": !1,
      "disable-outside-scroll": !1,
      onDismiss: i[0] || (i[0] = (l) => d(s).onOpenChange(!1))
    }), {
      default: S(() => [
        P(a.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), vg = /* @__PURE__ */ T({
  __name: "MenuContent",
  props: {
    forceMount: { type: Boolean },
    loop: { type: Boolean },
    side: {},
    sideOffset: {},
    align: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    disableUpdateOnLayoutShift: { type: Boolean },
    prioritizePosition: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "entryFocus", "openAutoFocus", "closeAutoFocus"],
  setup(e, { emit: t }) {
    const r = Ne(e, t), s = On(), a = Mo();
    return (i, l) => (_(), E(d(kt), {
      present: i.forceMount || d(s).open.value
    }, {
      default: S(() => [
        d(a).modal.value ? (_(), E(mg, me(z({ key: 0 }, { ...i.$attrs, ...d(r) })), {
          default: S(() => [
            P(i.$slots, "default")
          ]),
          _: 3
        }, 16)) : (_(), E(gg, me(z({ key: 1 }, { ...i.$attrs, ...d(r) })), {
          default: S(() => [
            P(i.$slots, "default")
          ]),
          _: 3
        }, 16))
      ]),
      _: 3
    }, 8, ["present"]));
  }
}), Vu = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "MenuItemImpl",
  props: {
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e, n = va(), { forwardRef: o } = X(), { CollectionItem: r } = rn(), s = B(!1);
    async function a(l) {
      if (!l.defaultPrevented && To(l)) {
        if (t.disabled)
          n.onItemLeave(l);
        else if (!n.onItemEnter(l)) {
          const c = l.currentTarget;
          c == null || c.focus({ preventScroll: !0 });
        }
      }
    }
    async function i(l) {
      await Le(), !l.defaultPrevented && To(l) && n.onItemLeave(l);
    }
    return (l, u) => (_(), E(d(r), {
      value: { textValue: l.textValue }
    }, {
      default: S(() => [
        W(d(ie), z({
          ref: d(o),
          role: "menuitem",
          tabindex: "-1"
        }, l.$attrs, {
          as: l.as,
          "as-child": l.asChild,
          "aria-disabled": l.disabled || void 0,
          "data-disabled": l.disabled ? "" : void 0,
          "data-highlighted": s.value ? "" : void 0,
          onPointermove: a,
          onPointerleave: i,
          onFocus: u[0] || (u[0] = async (c) => {
            await Le(), !(c.defaultPrevented || l.disabled) && (s.value = !0);
          }),
          onBlur: u[1] || (u[1] = async (c) => {
            await Le(), !c.defaultPrevented && (s.value = !1);
          })
        }), {
          default: S(() => [
            P(l.$slots, "default")
          ]),
          _: 3
        }, 16, ["as", "as-child", "aria-disabled", "data-disabled", "data-highlighted"])
      ]),
      _: 3
    }, 8, ["value"]));
  }
}), ba = /* @__PURE__ */ T({
  __name: "MenuItem",
  props: {
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["select"],
  setup(e, { emit: t }) {
    const n = e, o = t, { forwardRef: r, currentElement: s } = X(), a = Mo(), i = va(), l = B(!1);
    async function u() {
      const c = s.value;
      if (!n.disabled && c) {
        const f = new CustomEvent(gm, {
          bubbles: !0,
          cancelable: !0
        });
        o("select", f), await Le(), f.defaultPrevented ? l.value = !1 : a.onClose();
      }
    }
    return (c, f) => (_(), E(Vu, z(n, {
      ref: d(r),
      onClick: u,
      onPointerdown: f[0] || (f[0] = () => {
        l.value = !0;
      }),
      onPointerup: f[1] || (f[1] = async (p) => {
        var h;
        await Le(), !p.defaultPrevented && (l.value || (h = p.currentTarget) == null || h.click());
      }),
      onKeydown: f[2] || (f[2] = async (p) => {
        const h = d(i).searchRef.value !== "";
        c.disabled || h && p.key === " " || d($s).includes(p.key) && (p.currentTarget.click(), p.preventDefault());
      })
    }), {
      default: S(() => [
        P(c.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Nu = /* @__PURE__ */ T({
  __name: "MenuGroup",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(ie), z({ role: "group" }, t), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), yg = /* @__PURE__ */ T({
  __name: "MenuSeparator",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(ie), z(t, {
      role: "separator",
      "aria-orientation": "horizontal"
    }), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), [bg, zu] = Ve(
  ["MenuCheckboxItem", "MenuRadioItem"],
  "MenuItemIndicatorContext"
), wg = /* @__PURE__ */ T({
  __name: "MenuItemIndicator",
  props: {
    forceMount: { type: Boolean },
    asChild: { type: Boolean },
    as: { default: "span" }
  },
  setup(e) {
    const t = bg({
      modelValue: B(!1)
    });
    return (n, o) => (_(), E(d(kt), {
      present: n.forceMount || d(lr)(d(t).modelValue.value) || d(t).modelValue.value === !0
    }, {
      default: S(() => [
        W(d(ie), {
          as: n.as,
          "as-child": n.asChild,
          "data-state": d(ca)(d(t).modelValue.value)
        }, {
          default: S(() => [
            P(n.$slots, "default")
          ]),
          _: 3
        }, 8, ["as", "as-child", "data-state"])
      ]),
      _: 3
    }, 8, ["present"]));
  }
}), _g = /* @__PURE__ */ T({
  __name: "MenuCheckboxItem",
  props: {
    modelValue: { type: [Boolean, String], default: !1 },
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["select", "update:modelValue"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = vt(n, "modelValue", o);
    return zu({ modelValue: r }), (s, a) => (_(), E(ba, z({ role: "menuitemcheckbox" }, n, {
      "aria-checked": d(lr)(d(r)) ? "mixed" : d(r),
      "data-state": d(ca)(d(r)),
      onSelect: a[0] || (a[0] = async (i) => {
        o("select", i), d(lr)(d(r)) ? r.value = !0 : r.value = !d(r);
      })
    }), {
      default: S(() => [
        P(s.$slots, "default", { modelValue: d(r) })
      ]),
      _: 3
    }, 16, ["aria-checked", "data-state"]));
  }
}), xg = /* @__PURE__ */ T({
  __name: "MenuLabel",
  props: {
    asChild: { type: Boolean },
    as: { default: "div" }
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(ie), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), [Cg, Sg] = Ve("MenuRadioGroup"), $g = /* @__PURE__ */ T({
  __name: "MenuRadioGroup",
  props: {
    modelValue: { default: "" },
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["update:modelValue"],
  setup(e, { emit: t }) {
    const n = e, r = vt(n, "modelValue", t);
    return Sg({
      modelValue: r,
      onValueChange: (s) => {
        r.value = s;
      }
    }), (s, a) => (_(), E(Nu, me(we(n)), {
      default: S(() => [
        P(s.$slots, "default", { modelValue: d(r) })
      ]),
      _: 3
    }, 16));
  }
}), Tg = /* @__PURE__ */ T({
  __name: "MenuRadioItem",
  props: {
    value: {},
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["select"],
  setup(e, { emit: t }) {
    const n = e, o = t, { value: r } = Xe(n), s = Cg(), a = O(
      () => s.modelValue.value === (r == null ? void 0 : r.value)
    );
    return zu({ modelValue: a }), (i, l) => (_(), E(ba, z({ role: "menuitemradio" }, n, {
      "aria-checked": a.value,
      "data-state": d(ca)(a.value),
      onSelect: l[0] || (l[0] = async (u) => {
        o("select", u), d(s).onValueChange(d(r));
      })
    }), {
      default: S(() => [
        P(i.$slots, "default")
      ]),
      _: 3
    }, 16, ["aria-checked", "data-state"]));
  }
}), [ju, Pg] = Ve("MenuSub"), Eg = /* @__PURE__ */ T({
  __name: "MenuSub",
  props: {
    open: { type: Boolean, default: void 0 }
  },
  emits: ["update:open"],
  setup(e, { emit: t }) {
    const n = e, r = vt(n, "open", t, {
      defaultValue: !1,
      passive: n.open === void 0
    }), s = On(), a = B(), i = B();
    return Me((l) => {
      (s == null ? void 0 : s.open.value) === !1 && (r.value = !1), l(() => r.value = !1);
    }), Iu({
      open: r,
      onOpenChange: (l) => {
        r.value = l;
      },
      content: i,
      onContentChange: (l) => {
        i.value = l;
      }
    }), Pg({
      triggerId: "",
      contentId: "",
      trigger: a,
      onTriggerChange: (l) => {
        a.value = l;
      }
    }), (l, u) => (_(), E(d(Dr), null, {
      default: S(() => [
        P(l.$slots, "default")
      ]),
      _: 3
    }));
  }
}), Ag = /* @__PURE__ */ T({
  __name: "MenuSubContent",
  props: {
    forceMount: { type: Boolean },
    loop: { type: Boolean },
    sideOffset: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    disableUpdateOnLayoutShift: { type: Boolean },
    prioritizePosition: { type: Boolean, default: !0 },
    reference: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "entryFocus", "openAutoFocus", "closeAutoFocus"],
  setup(e, { emit: t }) {
    const r = Ne(e, t), s = On(), a = Mo(), i = ju(), { forwardRef: l, currentElement: u } = X();
    return i.contentId || (i.contentId = yt(void 0, "reka-menu-sub-content")), (c, f) => (_(), E(d(kt), {
      present: c.forceMount || d(s).open.value
    }, {
      default: S(() => [
        W(ya, z(d(r), {
          id: d(i).contentId,
          ref: d(l),
          "aria-labelledby": d(i).triggerId,
          align: "start",
          side: d(a).dir.value === "rtl" ? "left" : "right",
          "disable-outside-pointer-events": !1,
          "disable-outside-scroll": !1,
          "trap-focus": !1,
          onOpenAutoFocus: f[0] || (f[0] = Et((p) => {
            var h;
            d(a).isUsingKeyboardRef.value && ((h = d(u)) == null || h.focus());
          }, ["prevent"])),
          onCloseAutoFocus: f[1] || (f[1] = Et(() => {
          }, ["prevent"])),
          onFocusOutside: f[2] || (f[2] = (p) => {
            p.defaultPrevented || p.target !== d(i).trigger.value && d(s).onOpenChange(!1);
          }),
          onEscapeKeyDown: f[3] || (f[3] = (p) => {
            d(a).onClose(), p.preventDefault();
          }),
          onKeydown: f[4] || (f[4] = (p) => {
            var y, C;
            const h = (y = p.currentTarget) == null ? void 0 : y.contains(p.target), g = d(wm)[d(a).dir.value].includes(p.key);
            h && g && (d(s).onOpenChange(!1), (C = d(i).trigger.value) == null || C.focus(), p.preventDefault());
          })
        }), {
          default: S(() => [
            P(c.$slots, "default")
          ]),
          _: 3
        }, 16, ["id", "aria-labelledby", "side"])
      ]),
      _: 3
    }, 8, ["present"]));
  }
}), Og = /* @__PURE__ */ T({
  __name: "MenuSubTrigger",
  props: {
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e, n = On(), o = Mo(), r = ju(), s = va(), a = B(null);
    r.triggerId || (r.triggerId = yt(void 0, "reka-menu-sub-trigger"));
    function i() {
      a.value && window.clearTimeout(a.value), a.value = null;
    }
    bt(() => {
      i();
    });
    function l(f) {
      !To(f) || s.onItemEnter(f) || !t.disabled && !n.open.value && !a.value && (s.onPointerGraceIntentChange(null), a.value = window.setTimeout(() => {
        n.onOpenChange(!0), i();
      }, 100));
    }
    async function u(f) {
      var h, g;
      if (!To(f))
        return;
      i();
      const p = (h = n.content.value) == null ? void 0 : h.getBoundingClientRect();
      if (p != null && p.width) {
        const y = (g = n.content.value) == null ? void 0 : g.dataset.side, C = y === "right", v = C ? -5 : 5, w = p[C ? "left" : "right"], m = p[C ? "right" : "left"];
        s.onPointerGraceIntentChange({
          area: [
            // Apply a bleed on clientX to ensure that our exit point is
            // consistently within polygon bounds
            { x: f.clientX + v, y: f.clientY },
            { x: w, y: p.top },
            { x: m, y: p.top },
            { x: m, y: p.bottom },
            { x: w, y: p.bottom }
          ],
          side: y
        }), window.clearTimeout(s.pointerGraceTimerRef.value), s.pointerGraceTimerRef.value = window.setTimeout(
          () => s.onPointerGraceIntentChange(null),
          300
        );
      } else {
        if (s.onTriggerLeave(f))
          return;
        s.onPointerGraceIntentChange(null);
      }
    }
    async function c(f) {
      var h;
      const p = s.searchRef.value !== "";
      t.disabled || p && f.key === " " || bm[o.dir.value].includes(f.key) && (n.onOpenChange(!0), await Le(), (h = n.content.value) == null || h.focus(), f.preventDefault());
    }
    return (f, p) => (_(), E(Lu, { "as-child": "" }, {
      default: S(() => [
        W(Vu, z(t, {
          id: d(r).triggerId,
          ref: (h) => {
            var g;
            (g = d(r)) == null || g.onTriggerChange(h == null ? void 0 : h.$el);
          },
          "aria-haspopup": "menu",
          "aria-expanded": d(n).open.value,
          "aria-controls": d(r).contentId,
          "data-state": d(ua)(d(n).open.value),
          onClick: p[0] || (p[0] = async (h) => {
            t.disabled || h.defaultPrevented || (h.currentTarget.focus(), d(n).open.value || d(n).onOpenChange(!0));
          }),
          onPointermove: l,
          onPointerleave: u,
          onKeydown: c
        }), {
          default: S(() => [
            P(f.$slots, "default")
          ]),
          _: 3
        }, 16, ["id", "aria-expanded", "aria-controls", "data-state"])
      ]),
      _: 3
    }));
  }
}), Bg = /* @__PURE__ */ T({
  __name: "DialogPortal",
  props: {
    to: {},
    disabled: { type: Boolean },
    defer: { type: Boolean },
    forceMount: { type: Boolean }
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(kr), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), [Hu, kg] = Ve("DropdownMenuRoot"), Mg = /* @__PURE__ */ T({
  __name: "DropdownMenuRoot",
  props: {
    defaultOpen: { type: Boolean },
    open: { type: Boolean, default: void 0 },
    dir: {},
    modal: { type: Boolean, default: !0 }
  },
  emits: ["update:open"],
  setup(e, { emit: t }) {
    const n = e, o = t;
    X();
    const r = vt(n, "open", o, {
      defaultValue: n.defaultOpen,
      passive: n.open === void 0
    }), s = B(), { modal: a, dir: i } = Xe(n), l = to(i);
    return kg({
      open: r,
      onOpenChange: (u) => {
        r.value = u;
      },
      onOpenToggle: () => {
        r.value = !r.value;
      },
      triggerId: "",
      triggerElement: s,
      contentId: "",
      modal: a,
      dir: l
    }), (u, c) => (_(), E(d(fg), {
      open: d(r),
      "onUpdate:open": c[0] || (c[0] = (f) => He(r) ? r.value = f : null),
      dir: d(l),
      modal: d(a)
    }, {
      default: S(() => [
        P(u.$slots, "default", { open: d(r) })
      ]),
      _: 3
    }, 8, ["open", "dir", "modal"]));
  }
}), Dg = /* @__PURE__ */ T({
  __name: "DropdownMenuTrigger",
  props: {
    disabled: { type: Boolean },
    asChild: { type: Boolean },
    as: { default: "button" }
  },
  setup(e) {
    const t = e, n = Hu(), { forwardRef: o, currentElement: r } = X();
    return Se(() => {
      n.triggerElement = r;
    }), n.triggerId || (n.triggerId = yt(void 0, "reka-dropdown-menu-trigger")), (s, a) => (_(), E(d(Lu), { "as-child": "" }, {
      default: S(() => [
        W(d(ie), {
          id: d(n).triggerId,
          ref: d(o),
          type: s.as === "button" ? "button" : void 0,
          "as-child": t.asChild,
          as: s.as,
          "aria-haspopup": "menu",
          "aria-expanded": d(n).open.value,
          "aria-controls": d(n).open.value ? d(n).contentId : void 0,
          "data-disabled": s.disabled ? "" : void 0,
          disabled: s.disabled,
          "data-state": d(n).open.value ? "open" : "closed",
          onClick: a[0] || (a[0] = async (i) => {
            var l;
            !s.disabled && i.button === 0 && i.ctrlKey === !1 && ((l = d(n)) == null || l.onOpenToggle(), await Le(), d(n).open.value && i.preventDefault());
          }),
          onKeydown: a[1] || (a[1] = Xs(
            (i) => {
              s.disabled || (["Enter", " "].includes(i.key) && d(n).onOpenToggle(), i.key === "ArrowDown" && d(n).onOpenChange(!0), ["Enter", " ", "ArrowDown"].includes(i.key) && i.preventDefault());
            },
            ["enter", "space", "arrow-down"]
          ))
        }, {
          default: S(() => [
            P(s.$slots, "default")
          ]),
          _: 3
        }, 8, ["id", "type", "as-child", "as", "aria-expanded", "aria-controls", "data-disabled", "disabled", "data-state"])
      ]),
      _: 3
    }));
  }
}), Rg = /* @__PURE__ */ T({
  __name: "DropdownMenuPortal",
  props: {
    to: {},
    disabled: { type: Boolean },
    defer: { type: Boolean },
    forceMount: { type: Boolean }
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(pg), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Ig = /* @__PURE__ */ T({
  __name: "DropdownMenuContent",
  props: {
    forceMount: { type: Boolean },
    loop: { type: Boolean },
    side: {},
    sideOffset: {},
    align: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    disableUpdateOnLayoutShift: { type: Boolean },
    prioritizePosition: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "closeAutoFocus"],
  setup(e, { emit: t }) {
    const r = Ne(e, t);
    X();
    const s = Hu(), a = B(!1);
    function i(l) {
      l.defaultPrevented || (a.value || setTimeout(() => {
        var u;
        (u = s.triggerElement.value) == null || u.focus();
      }, 0), a.value = !1, l.preventDefault());
    }
    return s.contentId || (s.contentId = yt(void 0, "reka-dropdown-menu-content")), (l, u) => {
      var c;
      return _(), E(d(vg), z(d(r), {
        id: d(s).contentId,
        "aria-labelledby": (c = d(s)) == null ? void 0 : c.triggerId,
        style: {
          "--reka-dropdown-menu-content-transform-origin": "var(--reka-popper-transform-origin)",
          "--reka-dropdown-menu-content-available-width": "var(--reka-popper-available-width)",
          "--reka-dropdown-menu-content-available-height": "var(--reka-popper-available-height)",
          "--reka-dropdown-menu-trigger-width": "var(--reka-popper-anchor-width)",
          "--reka-dropdown-menu-trigger-height": "var(--reka-popper-anchor-height)"
        },
        onCloseAutoFocus: i,
        onInteractOutside: u[0] || (u[0] = (f) => {
          var y;
          if (f.defaultPrevented) return;
          const p = f.detail.originalEvent, h = p.button === 0 && p.ctrlKey === !0, g = p.button === 2 || h;
          (!d(s).modal.value || g) && (a.value = !0), (y = d(s).triggerElement.value) != null && y.contains(f.target) && f.preventDefault();
        })
      }), {
        default: S(() => [
          P(l.$slots, "default")
        ]),
        _: 3
      }, 16, ["id", "aria-labelledby"]);
    };
  }
}), Lg = /* @__PURE__ */ T({
  __name: "DropdownMenuItem",
  props: {
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["select"],
  setup(e, { emit: t }) {
    const n = e, r = An(t);
    return X(), (s, a) => (_(), E(d(ba), me(we({ ...n, ...d(r) })), {
      default: S(() => [
        P(s.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Fg = /* @__PURE__ */ T({
  __name: "DropdownMenuGroup",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return X(), (n, o) => (_(), E(d(Nu), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Vg = /* @__PURE__ */ T({
  __name: "DropdownMenuSeparator",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return X(), (n, o) => (_(), E(d(yg), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Ng = /* @__PURE__ */ T({
  __name: "DropdownMenuCheckboxItem",
  props: {
    modelValue: { type: [Boolean, String] },
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["select", "update:modelValue"],
  setup(e, { emit: t }) {
    const n = e, r = An(t);
    return X(), (s, a) => (_(), E(d(_g), me(we({ ...n, ...d(r) })), {
      default: S(() => [
        P(s.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Wu = /* @__PURE__ */ T({
  __name: "DropdownMenuItemIndicator",
  props: {
    forceMount: { type: Boolean },
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return X(), (n, o) => (_(), E(d(wg), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), zg = /* @__PURE__ */ T({
  __name: "DropdownMenuLabel",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return X(), (n, o) => (_(), E(d(xg), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), jg = /* @__PURE__ */ T({
  __name: "DropdownMenuRadioGroup",
  props: {
    modelValue: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["update:modelValue"],
  setup(e, { emit: t }) {
    const n = e, r = An(t);
    return X(), (s, a) => (_(), E(d($g), me(we({ ...n, ...d(r) })), {
      default: S(() => [
        P(s.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Hg = /* @__PURE__ */ T({
  __name: "DropdownMenuRadioItem",
  props: {
    value: {},
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["select"],
  setup(e, { emit: t }) {
    const r = Ne(e, t);
    return X(), (s, a) => (_(), E(d(Tg), me(we(d(r))), {
      default: S(() => [
        P(s.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Wg = /* @__PURE__ */ T({
  __name: "DropdownMenuSub",
  props: {
    defaultOpen: { type: Boolean },
    open: { type: Boolean, default: void 0 }
  },
  emits: ["update:open"],
  setup(e, { emit: t }) {
    const n = e, r = vt(n, "open", t, {
      passive: n.open === void 0,
      defaultValue: n.defaultOpen ?? !1
    });
    return X(), (s, a) => (_(), E(d(Eg), {
      open: d(r),
      "onUpdate:open": a[0] || (a[0] = (i) => He(r) ? r.value = i : null)
    }, {
      default: S(() => [
        P(s.$slots, "default", { open: d(r) })
      ]),
      _: 3
    }, 8, ["open"]));
  }
}), Kg = /* @__PURE__ */ T({
  __name: "DropdownMenuSubContent",
  props: {
    forceMount: { type: Boolean },
    loop: { type: Boolean },
    sideOffset: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    disableUpdateOnLayoutShift: { type: Boolean },
    prioritizePosition: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "entryFocus", "openAutoFocus", "closeAutoFocus"],
  setup(e, { emit: t }) {
    const r = Ne(e, t);
    return X(), (s, a) => (_(), E(d(Ag), z(d(r), { style: {
      "--reka-dropdown-menu-content-transform-origin": "var(--reka-popper-transform-origin)",
      "--reka-dropdown-menu-content-available-width": "var(--reka-popper-available-width)",
      "--reka-dropdown-menu-content-available-height": "var(--reka-popper-available-height)",
      "--reka-dropdown-menu-trigger-width": "var(--reka-popper-anchor-width)",
      "--reka-dropdown-menu-trigger-height": "var(--reka-popper-anchor-height)"
    } }), {
      default: S(() => [
        P(s.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Ug = /* @__PURE__ */ T({
  __name: "DropdownMenuSubTrigger",
  props: {
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return X(), (n, o) => (_(), E(d(Og), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
});
function Gg(e, t) {
  const n = vu(!1, 300), o = B(null), r = Nh();
  function s() {
    o.value = null, n.value = !1;
  }
  function a(i, l) {
    const u = i.currentTarget, c = { x: i.clientX, y: i.clientY }, f = Yg(c, u.getBoundingClientRect()), p = qg(c, f), h = Xg(l.getBoundingClientRect()), g = Zg([...p, ...h]);
    o.value = g, n.value = !0;
  }
  return Me((i) => {
    if (e.value && t.value) {
      const l = (c) => a(c, t.value), u = (c) => a(c, e.value);
      e.value.addEventListener("pointerleave", l), t.value.addEventListener("pointerleave", u), i(() => {
        var c, f;
        (c = e.value) == null || c.removeEventListener("pointerleave", l), (f = t.value) == null || f.removeEventListener("pointerleave", u);
      });
    }
  }), Me((i) => {
    var l;
    if (o.value) {
      const u = (c) => {
        var C, v;
        if (!o.value)
          return;
        const f = c.target, p = { x: c.clientX, y: c.clientY }, h = ((C = e.value) == null ? void 0 : C.contains(f)) || ((v = t.value) == null ? void 0 : v.contains(f)), g = !Jg(p, o.value), y = !!f.closest("[data-grace-area-trigger]");
        h ? s() : (g || y) && (s(), r.trigger());
      };
      (l = e.value) == null || l.ownerDocument.addEventListener("pointermove", u), i(() => {
        var c;
        return (c = e.value) == null ? void 0 : c.ownerDocument.removeEventListener("pointermove", u);
      });
    }
  }), {
    isPointerInTransit: n,
    onPointerExit: r.on
  };
}
function Yg(e, t) {
  const n = Math.abs(t.top - e.y), o = Math.abs(t.bottom - e.y), r = Math.abs(t.right - e.x), s = Math.abs(t.left - e.x);
  switch (Math.min(n, o, r, s)) {
    case s:
      return "left";
    case r:
      return "right";
    case n:
      return "top";
    case o:
      return "bottom";
    default:
      throw new Error("unreachable");
  }
}
function qg(e, t, n = 5) {
  const o = [];
  switch (t) {
    case "top":
      o.push(
        { x: e.x - n, y: e.y + n },
        { x: e.x + n, y: e.y + n }
      );
      break;
    case "bottom":
      o.push(
        { x: e.x - n, y: e.y - n },
        { x: e.x + n, y: e.y - n }
      );
      break;
    case "left":
      o.push(
        { x: e.x + n, y: e.y - n },
        { x: e.x + n, y: e.y + n }
      );
      break;
    case "right":
      o.push(
        { x: e.x - n, y: e.y - n },
        { x: e.x - n, y: e.y + n }
      );
      break;
  }
  return o;
}
function Xg(e) {
  const { top: t, right: n, bottom: o, left: r } = e;
  return [
    { x: r, y: t },
    { x: n, y: t },
    { x: n, y: o },
    { x: r, y: o }
  ];
}
function Jg(e, t) {
  const { x: n, y: o } = e;
  let r = !1;
  for (let s = 0, a = t.length - 1; s < t.length; a = s++) {
    const i = t[s].x, l = t[s].y, u = t[a].x, c = t[a].y;
    l > o != c > o && n < (u - i) * (o - l) / (c - l) + i && (r = !r);
  }
  return r;
}
function Zg(e) {
  const t = e.slice();
  return t.sort((n, o) => n.x < o.x ? -1 : n.x > o.x ? 1 : n.y < o.y ? -1 : n.y > o.y ? 1 : 0), Qg(t);
}
function Qg(e) {
  if (e.length <= 1)
    return e.slice();
  const t = [];
  for (let o = 0; o < e.length; o++) {
    const r = e[o];
    for (; t.length >= 2; ) {
      const s = t[t.length - 1], a = t[t.length - 2];
      if ((s.x - a.x) * (r.y - a.y) >= (s.y - a.y) * (r.x - a.x))
        t.pop();
      else break;
    }
    t.push(r);
  }
  t.pop();
  const n = [];
  for (let o = e.length - 1; o >= 0; o--) {
    const r = e[o];
    for (; n.length >= 2; ) {
      const s = n[n.length - 1], a = n[n.length - 2];
      if ((s.x - a.x) * (r.y - a.y) >= (s.y - a.y) * (r.x - a.x))
        n.pop();
      else break;
    }
    n.push(r);
  }
  return n.pop(), t.length === 1 && n.length === 1 && t[0].x === n[0].x && t[0].y === n[0].y ? t : t.concat(n);
}
const ev = /* @__PURE__ */ T({
  __name: "Label",
  props: {
    for: {},
    asChild: { type: Boolean },
    as: { default: "label" }
  },
  setup(e) {
    const t = e;
    return X(), (n, o) => (_(), E(d(ie), z(t, {
      onMousedown: o[0] || (o[0] = (r) => {
        !r.defaultPrevented && r.detail > 1 && r.preventDefault();
      })
    }), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
});
function Os(e, t = Number.NEGATIVE_INFINITY, n = Number.POSITIVE_INFINITY) {
  return Math.min(n, Math.max(t, e));
}
const [$t, tv] = Ve("ScrollAreaRoot"), nv = /* @__PURE__ */ T({
  __name: "ScrollAreaRoot",
  props: {
    type: { default: "hover" },
    dir: {},
    scrollHideDelay: { default: 600 },
    asChild: { type: Boolean },
    as: {}
  },
  setup(e, { expose: t }) {
    const n = e, o = B(0), r = B(0), s = B(), a = B(), i = B(), l = B(), u = B(!1), c = B(!1), { type: f, dir: p, scrollHideDelay: h } = Xe(n), g = to(p);
    function y() {
      var m;
      (m = s.value) == null || m.scrollTo({
        top: 0
      });
    }
    function C() {
      var m;
      (m = s.value) == null || m.scrollTo({
        top: 0,
        left: 0
      });
    }
    t({
      /** Viewport element within ScrollArea */
      viewport: s,
      /** Scroll viewport to top */
      scrollTop: y,
      /** Scroll viewport to top-left */
      scrollTopLeft: C
    });
    const { forwardRef: v, currentElement: w } = X();
    return tv({
      type: f,
      dir: g,
      scrollHideDelay: h,
      scrollArea: w,
      viewport: s,
      onViewportChange: (m) => {
        s.value = m || void 0;
      },
      content: a,
      onContentChange: (m) => {
        a.value = m;
      },
      scrollbarX: i,
      scrollbarXEnabled: u,
      scrollbarY: l,
      scrollbarYEnabled: c,
      onScrollbarXChange: (m) => {
        i.value = m || void 0;
      },
      onScrollbarYChange: (m) => {
        l.value = m || void 0;
      },
      onScrollbarXEnabledChange: (m) => {
        u.value = m;
      },
      onScrollbarYEnabledChange: (m) => {
        c.value = m;
      },
      onCornerWidthChange: (m) => {
        o.value = m;
      },
      onCornerHeightChange: (m) => {
        r.value = m;
      }
    }), (m, x) => (_(), E(d(ie), {
      ref: d(v),
      "as-child": n.asChild,
      as: m.as,
      dir: d(g),
      style: nt({
        position: "relative",
        // Pass corner sizes as CSS vars to reduce re-renders of context consumers
        "--reka-scroll-area-corner-width": `${o.value}px`,
        "--reka-scroll-area-corner-height": `${r.value}px`
      })
    }, {
      default: S(() => [
        P(m.$slots, "default")
      ]),
      _: 3
    }, 8, ["as-child", "as", "dir", "style"]));
  }
}), ov = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "ScrollAreaViewport",
  props: {
    nonce: {},
    asChild: { type: Boolean },
    as: {}
  },
  setup(e, { expose: t }) {
    const n = e, { nonce: o } = Xe(n), r = Ru(o), s = $t(), a = B();
    Se(() => {
      s.onViewportChange(a.value), s.onContentChange(l.value);
    }), t({
      viewportElement: a
    });
    const { forwardRef: i, currentElement: l } = X();
    return (u, c) => (_(), oe(ke, null, [
      Z("div", z({
        ref_key: "viewportElement",
        ref: a,
        "data-reka-scroll-area-viewport": "",
        style: {
          /**
           * We don't support `visible` because the intention is to have at least one scrollbar
           * if this component is used and `visible` will behave like `auto` in that case
           * https://developer.mozilla.org/en-US/docs/Web/CSS/overflowed#description
           *
           * We don't handle `auto` because the intention is for the native implementation
           * to be hidden if using this component. We just want to ensure the node is scrollable
           * so could have used either `scroll` or `auto` here. We picked `scroll` to prevent
           * the browser from having to work out whether to render native scrollbars or not,
           * we tell it to with the intention of hiding them in CSS.
           */
          overflowX: d(s).scrollbarXEnabled.value ? "scroll" : "hidden",
          overflowY: d(s).scrollbarYEnabled.value ? "scroll" : "hidden"
        }
      }, u.$attrs, { tabindex: 0 }), [
        W(d(ie), {
          ref: d(i),
          style: nt({
            /**
             * When horizontal scrollbar is visible: this element should be at least
             * as wide as its children for size calculations to work correctly.
             *
             * When horizontal scrollbar is NOT visible: this element's width should
             * be constrained by the parent container to enable `text-overflow: ellipsis`
             */
            minWidth: d(s).scrollbarXEnabled.value ? "fit-content" : void 0
          }),
          "as-child": n.asChild,
          as: u.as
        }, {
          default: S(() => [
            P(u.$slots, "default")
          ]),
          _: 3
        }, 8, ["style", "as-child", "as"])
      ], 16),
      W(d(ie), {
        as: "style",
        nonce: d(r)
      }, {
        default: S(() => c[0] || (c[0] = [
          At(" /* Hide scrollbars cross-browser and enable momentum scroll for touch devices */ [data-reka-scroll-area-viewport] { scrollbar-width:none; -ms-overflow-style:none; -webkit-overflow-scrolling:touch; } [data-reka-scroll-area-viewport]::-webkit-scrollbar { display:none; } ")
        ])),
        _: 1
      }, 8, ["nonce"])
    ], 64));
  }
});
function Ku(e, t) {
  return (n) => {
    if (e[0] === e[1] || t[0] === t[1])
      return t[0];
    const o = (t[1] - t[0]) / (e[1] - e[0]);
    return t[0] + o * (n - e[0]);
  };
}
function Rr(e) {
  const t = Uu(e.viewport, e.content), n = e.scrollbar.paddingStart + e.scrollbar.paddingEnd, o = (e.scrollbar.size - n) * t;
  return Math.max(o, 18);
}
function Uu(e, t) {
  const n = e / t;
  return Number.isNaN(n) ? 0 : n;
}
function rv(e, t = () => {
}) {
  let n = { left: e.scrollLeft, top: e.scrollTop }, o = 0;
  return function r() {
    const s = { left: e.scrollLeft, top: e.scrollTop }, a = n.left !== s.left, i = n.top !== s.top;
    (a || i) && t(), n = s, o = window.requestAnimationFrame(r);
  }(), () => window.cancelAnimationFrame(o);
}
function Oi(e, t, n = "ltr") {
  const o = Rr(t), r = t.scrollbar.paddingStart + t.scrollbar.paddingEnd, s = t.scrollbar.size - r, a = t.content - t.viewport, i = s - o, l = n === "ltr" ? [0, a] : [a * -1, 0], u = Os(
    e,
    l[0],
    l[1]
  );
  return Ku([0, a], [0, i])(u);
}
function Ho(e) {
  return e ? Number.parseInt(e, 10) : 0;
}
function sv(e, t, n, o = "ltr") {
  const r = Rr(n), s = r / 2, a = t || s, i = r - a, l = n.scrollbar.paddingStart + a, u = n.scrollbar.size - n.scrollbar.paddingEnd - i, c = n.content - n.viewport, f = o === "ltr" ? [0, c] : [c * -1, 0];
  return Ku(
    [l, u],
    f
  )(e);
}
function Bi(e, t) {
  return e > 0 && e < t;
}
const Gu = /* @__PURE__ */ T({
  __name: "ScrollAreaScrollbarImpl",
  props: {
    isHorizontal: { type: Boolean }
  },
  emits: ["onDragScroll", "onWheelScroll", "onThumbPointerDown"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = $t(), s = Ir(), a = Lr(), { forwardRef: i, currentElement: l } = X(), u = B(""), c = B();
    function f(v) {
      var w, m;
      if (c.value) {
        const x = v.clientX - ((w = c.value) == null ? void 0 : w.left), A = v.clientY - ((m = c.value) == null ? void 0 : m.top);
        o("onDragScroll", { x, y: A });
      }
    }
    function p(v) {
      v.button === 0 && (v.target.setPointerCapture(v.pointerId), c.value = l.value.getBoundingClientRect(), u.value = document.body.style.webkitUserSelect, document.body.style.webkitUserSelect = "none", r.viewport && (r.viewport.value.style.scrollBehavior = "auto"), f(v));
    }
    function h(v) {
      f(v);
    }
    function g(v) {
      const w = v.target;
      w.hasPointerCapture(v.pointerId) && w.releasePointerCapture(v.pointerId), document.body.style.webkitUserSelect = u.value, r.viewport && (r.viewport.value.style.scrollBehavior = ""), c.value = void 0;
    }
    function y(v) {
      var A;
      const w = v.target, m = (A = l.value) == null ? void 0 : A.contains(w), x = s.sizes.value.content - s.sizes.value.viewport;
      m && s.handleWheelScroll(v, x);
    }
    Se(() => {
      document.addEventListener("wheel", y, { passive: !1 });
    }), bt(() => {
      document.removeEventListener("wheel", y);
    });
    function C() {
      var v, w, m, x, A;
      l.value && (n.isHorizontal ? s.handleSizeChange({
        content: ((v = r.viewport.value) == null ? void 0 : v.scrollWidth) ?? 0,
        viewport: ((w = r.viewport.value) == null ? void 0 : w.offsetWidth) ?? 0,
        scrollbar: {
          size: l.value.clientWidth ?? 0,
          paddingStart: Ho(getComputedStyle(l.value).paddingLeft),
          paddingEnd: Ho(getComputedStyle(l.value).paddingRight)
        }
      }) : s.handleSizeChange({
        content: ((m = r.viewport.value) == null ? void 0 : m.scrollHeight) ?? 0,
        viewport: ((x = r.viewport.value) == null ? void 0 : x.offsetHeight) ?? 0,
        scrollbar: {
          size: ((A = l.value) == null ? void 0 : A.clientHeight) ?? 0,
          paddingStart: Ho(getComputedStyle(l.value).paddingLeft),
          paddingEnd: Ho(getComputedStyle(l.value).paddingRight)
        }
      }));
    }
    return Pn(l, C), Pn(r.content, C), (v, w) => (_(), E(d(ie), {
      ref: d(i),
      style: { position: "absolute" },
      "data-scrollbarimpl": "",
      as: d(a).as.value,
      "as-child": d(a).asChild.value,
      onPointerdown: p,
      onPointermove: h,
      onPointerup: g
    }, {
      default: S(() => [
        P(v.$slots, "default")
      ]),
      _: 3
    }, 8, ["as", "as-child"]));
  }
}), av = /* @__PURE__ */ T({
  __name: "ScrollAreaScrollbarX",
  setup(e) {
    const t = $t(), n = Ir(), { forwardRef: o, currentElement: r } = X();
    Se(() => {
      r.value && t.onScrollbarXChange(r.value);
    });
    const s = O(() => n.sizes.value);
    return (a, i) => (_(), E(Gu, {
      ref: d(o),
      "is-horizontal": !0,
      "data-orientation": "horizontal",
      style: nt({
        bottom: 0,
        left: d(t).dir.value === "rtl" ? "var(--reka-scroll-area-corner-width)" : 0,
        right: d(t).dir.value === "ltr" ? "var(--reka-scroll-area-corner-width)" : 0,
        "--reka-scroll-area-thumb-width": s.value ? `${d(Rr)(s.value)}px` : void 0
      }),
      onOnDragScroll: i[0] || (i[0] = (l) => d(n).onDragScroll(l.x))
    }, {
      default: S(() => [
        P(a.$slots, "default")
      ]),
      _: 3
    }, 8, ["style"]));
  }
}), iv = /* @__PURE__ */ T({
  __name: "ScrollAreaScrollbarY",
  setup(e) {
    const t = $t(), n = Ir(), { forwardRef: o, currentElement: r } = X();
    Se(() => {
      r.value && t.onScrollbarYChange(r.value);
    });
    const s = O(() => n.sizes.value);
    return (a, i) => (_(), E(Gu, {
      ref: d(o),
      "is-horizontal": !1,
      "data-orientation": "vertical",
      style: nt({
        top: 0,
        right: d(t).dir.value === "ltr" ? 0 : void 0,
        left: d(t).dir.value === "rtl" ? 0 : void 0,
        bottom: "var(--reka-scroll-area-corner-height)",
        "--reka-scroll-area-thumb-height": s.value ? `${d(Rr)(s.value)}px` : void 0
      }),
      onOnDragScroll: i[0] || (i[0] = (l) => d(n).onDragScroll(l.y))
    }, {
      default: S(() => [
        P(a.$slots, "default")
      ]),
      _: 3
    }, 8, ["style"]));
  }
}), [Ir, lv] = Ve("ScrollAreaScrollbarVisible"), wa = /* @__PURE__ */ T({
  __name: "ScrollAreaScrollbarVisible",
  setup(e) {
    const t = $t(), n = Lr(), { forwardRef: o } = X(), r = B({
      content: 0,
      viewport: 0,
      scrollbar: { size: 0, paddingStart: 0, paddingEnd: 0 }
    }), s = O(() => {
      const v = Uu(r.value.viewport, r.value.content);
      return v > 0 && v < 1;
    }), a = B(), i = B(0);
    function l(v, w) {
      if (h.value) {
        const m = t.viewport.value.scrollLeft + v.deltaY;
        t.viewport.value.scrollLeft = m, Bi(m, w) && v.preventDefault();
      } else {
        const m = t.viewport.value.scrollTop + v.deltaY;
        t.viewport.value.scrollTop = m, Bi(m, w) && v.preventDefault();
      }
    }
    function u(v, w) {
      h.value ? i.value = w.x : i.value = w.y;
    }
    function c(v) {
      i.value = 0;
    }
    function f(v) {
      r.value = v;
    }
    function p(v, w) {
      return sv(
        v,
        i.value,
        r.value,
        w
      );
    }
    const h = O(
      () => n.isHorizontal.value
    );
    function g(v) {
      h.value ? t.viewport.value.scrollLeft = p(
        v,
        t.dir.value
      ) : t.viewport.value.scrollTop = p(v);
    }
    function y() {
      if (h.value) {
        if (t.viewport.value && a.value) {
          const v = t.viewport.value.scrollLeft, w = Oi(
            v,
            r.value,
            t.dir.value
          );
          a.value.style.transform = `translate3d(${w}px, 0, 0)`;
        }
      } else if (t.viewport.value && a.value) {
        const v = t.viewport.value.scrollTop, w = Oi(v, r.value);
        a.value.style.transform = `translate3d(0, ${w}px, 0)`;
      }
    }
    function C(v) {
      a.value = v;
    }
    return lv({
      sizes: r,
      hasThumb: s,
      handleWheelScroll: l,
      handleThumbDown: u,
      handleThumbUp: c,
      handleSizeChange: f,
      onThumbPositionChange: y,
      onThumbChange: C,
      onDragScroll: g
    }), (v, w) => h.value ? (_(), E(av, z({ key: 0 }, v.$attrs, { ref: d(o) }), {
      default: S(() => [
        P(v.$slots, "default")
      ]),
      _: 3
    }, 16)) : (_(), E(iv, z({ key: 1 }, v.$attrs, { ref: d(o) }), {
      default: S(() => [
        P(v.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Yu = /* @__PURE__ */ T({
  __name: "ScrollAreaScrollbarAuto",
  props: {
    forceMount: { type: Boolean }
  },
  setup(e) {
    const t = $t(), n = Lr(), { forwardRef: o } = X(), r = B(!1), s = yu(() => {
      if (t.viewport.value) {
        const a = t.viewport.value.offsetWidth < t.viewport.value.scrollWidth, i = t.viewport.value.offsetHeight < t.viewport.value.scrollHeight;
        r.value = n.isHorizontal.value ? a : i;
      }
    }, 10);
    return Se(() => s()), Pn(t.viewport, s), Pn(t.content, s), (a, i) => (_(), E(d(kt), {
      present: a.forceMount || r.value
    }, {
      default: S(() => [
        W(wa, z(a.$attrs, {
          ref: d(o),
          "data-state": r.value ? "visible" : "hidden"
        }), {
          default: S(() => [
            P(a.$slots, "default")
          ]),
          _: 3
        }, 16, ["data-state"])
      ]),
      _: 3
    }, 8, ["present"]));
  }
}), uv = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "ScrollAreaScrollbarHover",
  props: {
    forceMount: { type: Boolean }
  },
  setup(e) {
    const t = $t(), { forwardRef: n } = X();
    let o;
    const r = B(!1);
    function s() {
      window.clearTimeout(o), r.value = !0;
    }
    function a() {
      o = window.setTimeout(() => {
        r.value = !1;
      }, t.scrollHideDelay.value);
    }
    return Se(() => {
      const i = t.scrollArea.value;
      i && (i.addEventListener("pointerenter", s), i.addEventListener("pointerleave", a));
    }), bt(() => {
      const i = t.scrollArea.value;
      i && (window.clearTimeout(o), i.removeEventListener("pointerenter", s), i.removeEventListener("pointerleave", a));
    }), (i, l) => (_(), E(d(kt), {
      present: i.forceMount || r.value
    }, {
      default: S(() => [
        W(Yu, z(i.$attrs, {
          ref: d(n),
          "data-state": r.value ? "visible" : "hidden"
        }), {
          default: S(() => [
            P(i.$slots, "default")
          ]),
          _: 3
        }, 16, ["data-state"])
      ]),
      _: 3
    }, 8, ["present"]));
  }
}), cv = /* @__PURE__ */ T({
  __name: "ScrollAreaScrollbarScroll",
  props: {
    forceMount: { type: Boolean }
  },
  setup(e) {
    const t = $t(), n = Lr(), { forwardRef: o } = X(), { state: r, dispatch: s } = xu("hidden", {
      hidden: {
        SCROLL: "scrolling"
      },
      scrolling: {
        SCROLL_END: "idle",
        POINTER_ENTER: "interacting"
      },
      interacting: {
        SCROLL: "interacting",
        POINTER_LEAVE: "idle"
      },
      idle: {
        HIDE: "hidden",
        SCROLL: "scrolling",
        POINTER_ENTER: "interacting"
      }
    });
    Me((i) => {
      if (r.value === "idle") {
        const l = window.setTimeout(
          () => s("HIDE"),
          t.scrollHideDelay.value
        );
        i(() => {
          window.clearTimeout(l);
        });
      }
    });
    const a = yu(() => s("SCROLL_END"), 100);
    return Me((i) => {
      const l = t.viewport.value, u = n.isHorizontal.value ? "scrollLeft" : "scrollTop";
      if (l) {
        let c = l[u];
        const f = () => {
          const p = l[u];
          c !== p && (s("SCROLL"), a()), c = p;
        };
        l.addEventListener("scroll", f), i(() => {
          l.removeEventListener("scroll", f);
        });
      }
    }), (i, l) => (_(), E(d(kt), {
      present: i.forceMount || d(r) !== "hidden"
    }, {
      default: S(() => [
        W(wa, z(i.$attrs, { ref: d(o) }), {
          default: S(() => [
            P(i.$slots, "default")
          ]),
          _: 3
        }, 16)
      ]),
      _: 3
    }, 8, ["present"]));
  }
}), [Lr, dv] = Ve("ScrollAreaScrollbar"), fv = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "ScrollAreaScrollbar",
  props: {
    orientation: { default: "vertical" },
    forceMount: { type: Boolean },
    asChild: { type: Boolean },
    as: { default: "div" }
  },
  setup(e) {
    const t = e, { forwardRef: n } = X(), o = $t(), r = O(() => t.orientation === "horizontal");
    be(
      r,
      () => {
        r.value ? o.onScrollbarXEnabledChange(!0) : o.onScrollbarYEnabledChange(!0);
      },
      { immediate: !0 }
    ), bt(() => {
      o.onScrollbarXEnabledChange(!1), o.onScrollbarYEnabledChange(!1);
    });
    const { orientation: s, forceMount: a, asChild: i, as: l } = Xe(t);
    return dv({
      orientation: s,
      forceMount: a,
      isHorizontal: r,
      as: l,
      asChild: i
    }), (u, c) => d(o).type.value === "hover" ? (_(), E(uv, z({ key: 0 }, u.$attrs, {
      ref: d(n),
      "force-mount": d(a)
    }), {
      default: S(() => [
        P(u.$slots, "default")
      ]),
      _: 3
    }, 16, ["force-mount"])) : d(o).type.value === "scroll" ? (_(), E(cv, z({ key: 1 }, u.$attrs, {
      ref: d(n),
      "force-mount": d(a)
    }), {
      default: S(() => [
        P(u.$slots, "default")
      ]),
      _: 3
    }, 16, ["force-mount"])) : d(o).type.value === "auto" ? (_(), E(Yu, z({ key: 2 }, u.$attrs, {
      ref: d(n),
      "force-mount": d(a)
    }), {
      default: S(() => [
        P(u.$slots, "default")
      ]),
      _: 3
    }, 16, ["force-mount"])) : d(o).type.value === "always" ? (_(), E(wa, z({ key: 3 }, u.$attrs, {
      ref: d(n),
      "data-state": "visible"
    }), {
      default: S(() => [
        P(u.$slots, "default")
      ]),
      _: 3
    }, 16)) : Be("", !0);
  }
}), pv = /* @__PURE__ */ T({
  __name: "ScrollAreaThumb",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e, n = $t(), o = Ir();
    function r(p) {
      const g = p.target.getBoundingClientRect(), y = p.clientX - g.left, C = p.clientY - g.top;
      o.handleThumbDown(p, { x: y, y: C });
    }
    function s(p) {
      o.handleThumbUp(p);
    }
    const { forwardRef: a, currentElement: i } = X(), l = B(), u = O(() => n.viewport.value);
    function c() {
      if (!l.value) {
        const p = rv(
          u.value,
          o.onThumbPositionChange
        );
        l.value = p, o.onThumbPositionChange();
      }
    }
    const f = O(() => o.sizes.value);
    return Zh(f, () => {
      o.onThumbChange(i.value), u.value && (o.onThumbPositionChange(), u.value.addEventListener("scroll", c));
    }), bt(() => {
      var p;
      u.value.removeEventListener("scroll", c), (p = n.viewport.value) == null || p.removeEventListener("scroll", c);
    }), (p, h) => (_(), E(d(ie), {
      ref: d(a),
      "data-state": d(o).hasThumb ? "visible" : "hidden",
      style: {
        width: "var(--reka-scroll-area-thumb-width)",
        height: "var(--reka-scroll-area-thumb-height)"
      },
      "as-child": t.asChild,
      as: p.as,
      onPointerdown: r,
      onPointerup: s
    }, {
      default: S(() => [
        P(p.$slots, "default")
      ]),
      _: 3
    }, 8, ["data-state", "as-child", "as"]));
  }
}), hv = /* @__PURE__ */ T({
  __name: "ScrollAreaCornerImpl",
  setup(e) {
    const t = $t(), n = B(0), o = B(0), r = O(() => !!n.value && !!o.value);
    function s() {
      var l;
      const i = ((l = t.scrollbarX.value) == null ? void 0 : l.offsetHeight) || 0;
      t.onCornerHeightChange(i), o.value = i;
    }
    function a() {
      var l;
      const i = ((l = t.scrollbarY.value) == null ? void 0 : l.offsetWidth) || 0;
      t.onCornerWidthChange(i), n.value = i;
    }
    return Pn(t.scrollbarX.value, s), Pn(t.scrollbarY.value, a), be(() => t.scrollbarX.value, s), be(() => t.scrollbarY.value, a), (i, l) => {
      var u;
      return r.value ? (_(), E(d(ie), z({
        key: 0,
        style: {
          width: `${n.value}px`,
          height: `${o.value}px`,
          position: "absolute",
          right: d(t).dir.value === "ltr" ? 0 : void 0,
          left: d(t).dir.value === "rtl" ? 0 : void 0,
          bottom: 0
        }
      }, (u = i.$parent) == null ? void 0 : u.$props), {
        default: S(() => [
          P(i.$slots, "default")
        ]),
        _: 3
      }, 16, ["style"])) : Be("", !0);
    };
  }
}), mv = /* @__PURE__ */ T({
  __name: "ScrollAreaCorner",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e, { forwardRef: n } = X(), o = $t(), r = O(
      () => !!o.scrollbarX.value && !!o.scrollbarY.value
    ), s = O(
      () => o.type.value !== "scroll" && r.value
    );
    return (a, i) => s.value ? (_(), E(hv, z({ key: 0 }, t, { ref: d(n) }), {
      default: S(() => [
        P(a.$slots, "default")
      ]),
      _: 3
    }, 16)) : Be("", !0);
  }
}), gv = /* @__PURE__ */ T({
  __name: "BubbleSelect",
  props: {
    autocomplete: {},
    autofocus: { type: Boolean },
    disabled: { type: Boolean },
    form: {},
    multiple: { type: Boolean },
    name: {},
    required: { type: Boolean },
    size: {},
    value: {}
  },
  setup(e) {
    const t = e, n = B();
    return be(() => t.value, (o, r) => {
      var l;
      const s = window.HTMLSelectElement.prototype, i = Object.getOwnPropertyDescriptor(
        s,
        "value"
      ).set;
      if (o !== r && i) {
        const u = new Event("change", { bubbles: !0 });
        i.call(n.value, o), (l = n.value) == null || l.dispatchEvent(u);
      }
    }), (o, r) => (_(), E(d(ia), { "as-child": "" }, {
      default: S(() => [
        Z("select", z({
          ref_key: "selectElement",
          ref: n
        }, t), [
          P(o.$slots, "default")
        ], 16)
      ]),
      _: 3
    }));
  }
}), vv = [" ", "Enter", "ArrowUp", "ArrowDown"], yv = [" ", "Enter"], Tt = 10;
function Bs(e, t, n) {
  return e === void 0 ? !1 : Array.isArray(e) ? e.some((o) => ks(o, t, n)) : ks(e, t, n);
}
function ks(e, t, n) {
  return e === void 0 || t === void 0 ? !1 : typeof e == "string" ? e === t : typeof n == "function" ? n(e, t) : typeof n == "string" ? (e == null ? void 0 : e[n]) === (t == null ? void 0 : t[n]) : rm(e, t);
}
const bv = {
  key: 0,
  value: ""
}, [Bn, qu] = Ve("SelectRoot"), wv = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "SelectRoot",
  props: {
    open: { type: Boolean, default: void 0 },
    defaultOpen: { type: Boolean },
    defaultValue: {},
    modelValue: { default: void 0 },
    by: {},
    dir: {},
    multiple: { type: Boolean },
    autocomplete: {},
    disabled: { type: Boolean },
    name: {},
    required: { type: Boolean }
  },
  emits: ["update:modelValue", "update:open"],
  setup(e, { emit: t }) {
    const n = e, o = t, { required: r, disabled: s, multiple: a, dir: i } = Xe(n), l = vt(n, "modelValue", o, {
      defaultValue: n.defaultValue ?? (a.value ? [] : void 0),
      passive: n.modelValue === void 0,
      deep: !0
    }), u = vt(n, "open", o, {
      defaultValue: n.defaultOpen,
      passive: n.open === void 0
    }), c = B(), f = B(), p = B({
      x: 0,
      y: 0
    }), h = O(() => {
      var m;
      return a.value && Array.isArray(l.value) ? ((m = l.value) == null ? void 0 : m.length) === 0 : Si(l.value);
    });
    rn({ isProvider: !0 });
    const g = to(i), y = ku(c), C = B(/* @__PURE__ */ new Set()), v = O(() => Array.from(C.value).map((m) => m.value).join(";"));
    function w(m) {
      if (a.value) {
        const x = Array.isArray(l.value) ? [...l.value] : [], A = x.findIndex((R) => ks(R, m, n.by));
        A === -1 ? x.push(m) : x.splice(A, 1), l.value = [...x];
      } else
        l.value = m;
    }
    return qu({
      triggerElement: c,
      onTriggerChange: (m) => {
        c.value = m;
      },
      valueElement: f,
      onValueElementChange: (m) => {
        f.value = m;
      },
      contentId: "",
      modelValue: l,
      // @ts-expect-error Missing infer for AcceptableValue
      onValueChange: w,
      by: n.by,
      open: u,
      multiple: a,
      required: r,
      onOpenChange: (m) => {
        u.value = m;
      },
      dir: g,
      triggerPointerDownPosRef: p,
      disabled: s,
      isEmptyModelValue: h,
      optionsSet: C,
      onOptionAdd: (m) => C.value.add(m),
      onOptionRemove: (m) => C.value.delete(m)
    }), (m, x) => (_(), E(d(Dr), null, {
      default: S(() => [
        P(m.$slots, "default", {
          modelValue: d(l),
          open: d(u)
        }),
        d(y) ? (_(), E(gv, {
          key: v.value,
          "aria-hidden": "true",
          tabindex: "-1",
          multiple: d(a),
          required: d(r),
          name: m.name,
          autocomplete: m.autocomplete,
          disabled: d(s),
          value: d(l)
        }, {
          default: S(() => [
            d(Si)(d(l)) ? (_(), oe("option", bv)) : Be("", !0),
            (_(!0), oe(ke, null, wo(Array.from(C.value), (A) => (_(), oe("option", z({
              key: A.value ?? "",
              ref_for: !0
            }, A), null, 16))), 128))
          ]),
          _: 1
        }, 8, ["multiple", "required", "name", "autocomplete", "disabled", "value"])) : Be("", !0)
      ]),
      _: 3
    }));
  }
}), _v = /* @__PURE__ */ T({
  __name: "SelectTrigger",
  props: {
    disabled: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: { default: "button" }
  },
  setup(e) {
    const t = e, n = Bn(), { forwardRef: o, currentElement: r } = X(), s = O(() => {
      var p;
      return ((p = n.disabled) == null ? void 0 : p.value) || t.disabled;
    });
    n.contentId || (n.contentId = yt(void 0, "reka-select-content")), Se(() => {
      n.onTriggerChange(r.value);
    });
    const { getItems: a } = rn(), { search: i, handleTypeaheadSearch: l, resetTypeahead: u } = ha();
    function c() {
      s.value || (n.onOpenChange(!0), u());
    }
    function f(p) {
      c(), n.triggerPointerDownPosRef.value = {
        x: Math.round(p.pageX),
        y: Math.round(p.pageY)
      };
    }
    return (p, h) => (_(), E(d(ma), {
      "as-child": "",
      reference: p.reference
    }, {
      default: S(() => {
        var g, y, C, v;
        return [
          W(d(ie), {
            ref: d(o),
            role: "combobox",
            type: p.as === "button" ? "button" : void 0,
            "aria-controls": d(n).contentId,
            "aria-expanded": d(n).open.value || !1,
            "aria-required": (g = d(n).required) == null ? void 0 : g.value,
            "aria-autocomplete": "none",
            disabled: s.value,
            dir: (y = d(n)) == null ? void 0 : y.dir.value,
            "data-state": (C = d(n)) != null && C.open.value ? "open" : "closed",
            "data-disabled": s.value ? "" : void 0,
            "data-placeholder": (v = d(n).modelValue) != null && v.value ? void 0 : "",
            "as-child": p.asChild,
            as: p.as,
            onClick: h[0] || (h[0] = (w) => {
              var m;
              (m = w == null ? void 0 : w.currentTarget) == null || m.focus();
            }),
            onPointerdown: h[1] || (h[1] = (w) => {
              if (w.pointerType === "touch")
                return w.preventDefault();
              const m = w.target;
              m.hasPointerCapture(w.pointerId) && m.releasePointerCapture(w.pointerId), w.button === 0 && w.ctrlKey === !1 && (f(w), w.preventDefault());
            }),
            onPointerup: h[2] || (h[2] = Et(
              (w) => {
                w.pointerType === "touch" && f(w);
              },
              ["prevent"]
            )),
            onKeydown: h[3] || (h[3] = (w) => {
              const m = d(i) !== "";
              !(w.ctrlKey || w.altKey || w.metaKey) && w.key.length === 1 && m && w.key === " " || (d(l)(w.key, d(a)()), d(vv).includes(w.key) && (c(), w.preventDefault()));
            })
          }, {
            default: S(() => [
              P(p.$slots, "default")
            ]),
            _: 3
          }, 8, ["type", "aria-controls", "aria-expanded", "aria-required", "disabled", "dir", "data-state", "data-disabled", "data-placeholder", "as-child", "as"])
        ];
      }),
      _: 3
    }, 8, ["reference"]));
  }
}), xv = /* @__PURE__ */ T({
  __name: "SelectPortal",
  props: {
    to: {},
    disabled: { type: Boolean },
    defer: { type: Boolean },
    forceMount: { type: Boolean }
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(kr), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), [_a, Cv] = Ve("SelectItemAlignedPosition"), Sv = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "SelectItemAlignedPosition",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["placed"],
  setup(e, { emit: t }) {
    const n = e, o = t, { getItems: r } = rn(), s = Bn(), a = kn(), i = B(!1), l = B(!0), u = B(), { forwardRef: c, currentElement: f } = X(), { viewport: p, selectedItem: h, selectedItemText: g, focusSelectedItem: y } = a;
    function C() {
      if (s.triggerElement.value && s.valueElement.value && u.value && f.value && (p != null && p.value) && (h != null && h.value) && (g != null && g.value)) {
        const m = s.triggerElement.value.getBoundingClientRect(), x = f.value.getBoundingClientRect(), A = s.valueElement.value.getBoundingClientRect(), R = g.value.getBoundingClientRect();
        if (s.dir.value !== "rtl") {
          const _e = R.left - x.left, Ee = A.left - _e, $e = m.left - Ee, De = m.width + $e, Kt = Math.max(De, x.width), b = window.innerWidth - Tt, $ = Os(Ee, Tt, Math.max(Tt, b - Kt));
          u.value.style.minWidth = `${De}px`, u.value.style.left = `${$}px`;
        } else {
          const _e = x.right - R.right, Ee = window.innerWidth - A.right - _e, $e = window.innerWidth - m.right - Ee, De = m.width + $e, Kt = Math.max(De, x.width), b = window.innerWidth - Tt, $ = Os(
            Ee,
            Tt,
            Math.max(Tt, b - Kt)
          );
          u.value.style.minWidth = `${De}px`, u.value.style.right = `${$}px`;
        }
        const I = r().map((_e) => _e.ref), H = window.innerHeight - Tt * 2, k = p.value.scrollHeight, j = window.getComputedStyle(f.value), K = Number.parseInt(
          j.borderTopWidth,
          10
        ), J = Number.parseInt(j.paddingTop, 10), ge = Number.parseInt(
          j.borderBottomWidth,
          10
        ), ce = Number.parseInt(
          j.paddingBottom,
          10
        ), Pe = K + J + k + ce + ge, ue = Math.min(
          h.value.offsetHeight * 5,
          Pe
        ), ee = window.getComputedStyle(p.value), ne = Number.parseInt(ee.paddingTop, 10), he = Number.parseInt(
          ee.paddingBottom,
          10
        ), je = m.top + m.height / 2 - Tt, ze = H - je, Je = h.value.offsetHeight / 2, Mn = h.value.offsetTop + Je, sn = K + J + Mn, D = Pe - sn;
        if (sn <= je) {
          const _e = h.value === I[I.length - 1];
          u.value.style.bottom = "0px";
          const Ee = f.value.clientHeight - p.value.offsetTop - p.value.offsetHeight, $e = Math.max(
            ze,
            Je + (_e ? he : 0) + Ee + ge
          ), De = sn + $e;
          u.value.style.height = `${De}px`;
        } else {
          const _e = h.value === I[0];
          u.value.style.top = "0px";
          const $e = Math.max(
            je,
            K + p.value.offsetTop + (_e ? ne : 0) + Je
          ) + D;
          u.value.style.height = `${$e}px`, p.value.scrollTop = sn - je + p.value.offsetTop;
        }
        u.value.style.margin = `${Tt}px 0`, u.value.style.minHeight = `${ue}px`, u.value.style.maxHeight = `${H}px`, o("placed"), requestAnimationFrame(() => i.value = !0);
      }
    }
    const v = B("");
    Se(async () => {
      await Le(), C(), f.value && (v.value = window.getComputedStyle(f.value).zIndex);
    });
    function w(m) {
      m && l.value === !0 && (C(), y == null || y(), l.value = !1);
    }
    return Pn(s.triggerElement, () => {
      C();
    }), Cv({
      contentWrapper: u,
      shouldExpandOnScrollRef: i,
      onScrollButtonChange: w
    }), (m, x) => (_(), oe("div", {
      ref_key: "contentWrapperElement",
      ref: u,
      style: nt({
        display: "flex",
        flexDirection: "column",
        position: "fixed",
        zIndex: v.value
      })
    }, [
      W(d(ie), z({
        ref: d(c),
        style: {
          // When we get the height of the content, it includes borders. If we were to set
          // the height without having `boxSizing: 'border-box'` it would be too big.
          boxSizing: "border-box",
          // We need to ensure the content doesn't get taller than the wrapper
          maxHeight: "100%"
        }
      }, { ...m.$attrs, ...n }), {
        default: S(() => [
          P(m.$slots, "default")
        ]),
        _: 3
      }, 16)
    ], 4));
  }
}), $v = /* @__PURE__ */ T({
  __name: "SelectPopperPosition",
  props: {
    side: {},
    sideOffset: {},
    align: { default: "start" },
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: { default: Tt },
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    disableUpdateOnLayoutShift: { type: Boolean },
    prioritizePosition: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const n = wt(e);
    return (o, r) => (_(), E(d(ga), z(d(n), { style: {
      // Ensure border-box for floating-ui calculations
      boxSizing: "border-box",
      "--reka-select-content-transform-origin": "var(--reka-popper-transform-origin)",
      "--reka-select-content-available-width": "var(--reka-popper-available-width)",
      "--reka-select-content-available-height": "var(--reka-popper-available-height)",
      "--reka-select-trigger-width": "var(--reka-popper-anchor-width)",
      "--reka-select-trigger-height": "var(--reka-popper-anchor-height)"
    } }), {
      default: S(() => [
        P(o.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Tv = {
  onViewportChange: () => {
  },
  itemTextRefCallback: () => {
  },
  itemRefCallback: () => {
  }
}, [kn, Xu] = Ve("SelectContent"), Pv = /* @__PURE__ */ T({
  __name: "SelectContentImpl",
  props: {
    position: { default: "item-aligned" },
    bodyLock: { type: Boolean, default: !0 },
    side: {},
    sideOffset: {},
    align: { default: "start" },
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    disableUpdateOnLayoutShift: { type: Boolean },
    prioritizePosition: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["closeAutoFocus", "escapeKeyDown", "pointerDownOutside"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = Bn();
    Fu(), pa(n.bodyLock);
    const { CollectionSlot: s, getItems: a } = rn(), i = B();
    fa(i);
    const { search: l, handleTypeaheadSearch: u } = ha(), c = B(), f = B(), p = B(), h = B(!1), g = B(!1), y = B(!1);
    function C() {
      f.value && i.value && Ts([f.value, i.value]);
    }
    be(h, () => {
      C();
    });
    const { onOpenChange: v, triggerPointerDownPosRef: w } = r;
    Me((R) => {
      if (!i.value)
        return;
      let I = { x: 0, y: 0 };
      const H = (j) => {
        var K, J;
        I = {
          x: Math.abs(
            Math.round(j.pageX) - (((K = w.value) == null ? void 0 : K.x) ?? 0)
          ),
          y: Math.abs(
            Math.round(j.pageY) - (((J = w.value) == null ? void 0 : J.y) ?? 0)
          )
        };
      }, k = (j) => {
        var K;
        j.pointerType !== "touch" && (I.x <= 10 && I.y <= 10 ? j.preventDefault() : (K = i.value) != null && K.contains(j.target) || v(!1), document.removeEventListener("pointermove", H), w.value = null);
      };
      w.value !== null && (document.addEventListener("pointermove", H), document.addEventListener("pointerup", k, {
        capture: !0,
        once: !0
      })), R(() => {
        document.removeEventListener("pointermove", H), document.removeEventListener("pointerup", k, {
          capture: !0
        });
      });
    });
    function m(R) {
      const I = R.ctrlKey || R.altKey || R.metaKey;
      if (R.key === "Tab" && R.preventDefault(), !I && R.key.length === 1 && u(R.key, a()), ["ArrowUp", "ArrowDown", "Home", "End"].includes(R.key)) {
        let k = [...a().map((j) => j.ref)];
        if (["ArrowUp", "End"].includes(R.key) && (k = k.slice().reverse()), ["ArrowUp", "ArrowDown"].includes(R.key)) {
          const j = R.target, K = k.indexOf(j);
          k = k.slice(K + 1);
        }
        setTimeout(() => Ts(k)), R.preventDefault();
      }
    }
    const x = O(() => n.position === "popper" ? n : {}), A = wt(x.value);
    return Xu({
      content: i,
      viewport: c,
      onViewportChange: (R) => {
        c.value = R;
      },
      itemRefCallback: (R, I, H) => {
        const k = !g.value && !H, j = Bs(r.modelValue.value, I, r.by);
        if (r.multiple.value) {
          if (y.value)
            return;
          (j || k) && (f.value = R, j && (y.value = !0));
        } else
          (j || k) && (f.value = R);
        k && (g.value = !0);
      },
      selectedItem: f,
      selectedItemText: p,
      onItemLeave: () => {
        var R;
        (R = i.value) == null || R.focus();
      },
      itemTextRefCallback: (R, I, H) => {
        const k = !g.value && !H;
        (Bs(r.modelValue.value, I, r.by) || k) && (p.value = R);
      },
      focusSelectedItem: C,
      position: n.position,
      isPositioned: h,
      searchRef: l
    }), (R, I) => (_(), E(d(s), null, {
      default: S(() => [
        W(d(da), {
          "as-child": "",
          onMountAutoFocus: I[6] || (I[6] = Et(() => {
          }, ["prevent"])),
          onUnmountAutoFocus: I[7] || (I[7] = (H) => {
            var k;
            o("closeAutoFocus", H), !H.defaultPrevented && ((k = d(r).triggerElement.value) == null || k.focus({ preventScroll: !0 }), H.preventDefault());
          })
        }, {
          default: S(() => [
            W(d(Mr), {
              "as-child": "",
              "disable-outside-pointer-events": "",
              onFocusOutside: I[2] || (I[2] = Et(() => {
              }, ["prevent"])),
              onDismiss: I[3] || (I[3] = (H) => d(r).onOpenChange(!1)),
              onEscapeKeyDown: I[4] || (I[4] = (H) => o("escapeKeyDown", H)),
              onPointerDownOutside: I[5] || (I[5] = (H) => o("pointerDownOutside", H))
            }, {
              default: S(() => [
                (_(), E(_t(
                  R.position === "popper" ? $v : Sv
                ), z({ ...R.$attrs, ...d(A) }, {
                  id: d(r).contentId,
                  ref: (H) => {
                    i.value = d(zt)(H);
                  },
                  role: "listbox",
                  "data-state": d(r).open.value ? "open" : "closed",
                  dir: d(r).dir.value,
                  style: {
                    // flex layout so we can place the scroll buttons properly
                    display: "flex",
                    flexDirection: "column",
                    // reset the outline by default as the content MAY get focused
                    outline: "none"
                  },
                  onContextmenu: I[0] || (I[0] = Et(() => {
                  }, ["prevent"])),
                  onPlaced: I[1] || (I[1] = (H) => h.value = !0),
                  onKeydown: m
                }), {
                  default: S(() => [
                    P(R.$slots, "default")
                  ]),
                  _: 3
                }, 16, ["id", "data-state", "dir", "onKeydown"]))
              ]),
              _: 3
            })
          ]),
          _: 3
        })
      ]),
      _: 3
    }));
  }
}), Ev = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "SelectProvider",
  props: {
    context: {}
  },
  setup(e) {
    return qu(e.context), Xu(Tv), (n, o) => P(n.$slots, "default");
  }
}), Av = { key: 1 }, Ov = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "SelectContent",
  props: {
    forceMount: { type: Boolean },
    position: {},
    bodyLock: { type: Boolean },
    side: {},
    sideOffset: {},
    align: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    disableUpdateOnLayoutShift: { type: Boolean },
    prioritizePosition: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["closeAutoFocus", "escapeKeyDown", "pointerDownOutside"],
  setup(e, { emit: t }) {
    const n = e, r = Ne(n, t), s = Bn(), a = B();
    Se(() => {
      a.value = new DocumentFragment();
    });
    const i = B(), l = O(() => n.forceMount || s.open.value);
    return (u, c) => {
      var f;
      return l.value ? (_(), E(d(kt), {
        key: 0,
        ref_key: "presenceRef",
        ref: i,
        present: !0
      }, {
        default: S(() => [
          W(Pv, me(we({ ...d(r), ...u.$attrs })), {
            default: S(() => [
              P(u.$slots, "default")
            ]),
            _: 3
          }, 16)
        ]),
        _: 3
      }, 512)) : !((f = i.value) != null && f.present) && a.value ? (_(), oe("div", Av, [
        (_(), E(wl, { to: a.value }, [
          W(Ev, { context: d(s) }, {
            default: S(() => [
              P(u.$slots, "default")
            ]),
            _: 3
          }, 8, ["context"])
        ], 8, ["to"]))
      ])) : Be("", !0);
    };
  }
}), Bv = /* @__PURE__ */ T({
  __name: "SelectSeparator",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(ie), z({ "aria-hidden": "true" }, t), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), [Ju, kv] = Ve("SelectItem"), Mv = /* @__PURE__ */ T({
  __name: "SelectItem",
  props: {
    value: {},
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["select"],
  setup(e, { emit: t }) {
    const n = e, o = t, { disabled: r } = Xe(n), s = Bn(), a = kn(), { forwardRef: i, currentElement: l } = X(), { CollectionItem: u } = rn(), c = O(() => {
      var x;
      return Bs((x = s.modelValue) == null ? void 0 : x.value, n.value, s.by);
    }), f = B(!1), p = B(n.textValue ?? ""), h = yt(void 0, "reka-select-item-text"), g = "select.select";
    async function y(x) {
      if (x.defaultPrevented)
        return;
      const A = { originalEvent: x, value: n.value };
      la(g, C, A);
    }
    async function C(x) {
      await Le(), o("select", x), !x.defaultPrevented && (r.value || (s.onValueChange(n.value), s.multiple.value || s.onOpenChange(!1)));
    }
    async function v(x) {
      var A;
      await Le(), !x.defaultPrevented && (r.value ? (A = a.onItemLeave) == null || A.call(a) : x.currentTarget.focus({ preventScroll: !0 }));
    }
    async function w(x) {
      var A;
      await Le(), !x.defaultPrevented && x.currentTarget === ot() && ((A = a.onItemLeave) == null || A.call(a));
    }
    async function m(x) {
      var R;
      await Le(), !(x.defaultPrevented || ((R = a.searchRef) == null ? void 0 : R.value) !== "" && x.key === " ") && (yv.includes(x.key) && y(x), x.key === " " && x.preventDefault());
    }
    if (n.value === "")
      throw new Error(
        "A <SelectItem /> must have a value prop that is not an empty string. This is because the Select value can be set to an empty string to clear the selection and show the placeholder."
      );
    return Se(() => {
      l.value && a.itemRefCallback(
        l.value,
        n.value,
        n.disabled
      );
    }), kv({
      value: n.value,
      disabled: r,
      textId: h,
      isSelected: c,
      onItemTextChange: (x) => {
        p.value = ((p.value || (x == null ? void 0 : x.textContent)) ?? "").trim();
      }
    }), (x, A) => (_(), E(d(u), {
      value: { textValue: p.value }
    }, {
      default: S(() => [
        W(d(ie), {
          ref: d(i),
          role: "option",
          "aria-labelledby": d(h),
          "data-highlighted": f.value ? "" : void 0,
          "aria-selected": c.value,
          "data-state": c.value ? "checked" : "unchecked",
          "aria-disabled": d(r) || void 0,
          "data-disabled": d(r) ? "" : void 0,
          tabindex: d(r) ? void 0 : -1,
          as: x.as,
          "as-child": x.asChild,
          onFocus: A[0] || (A[0] = (R) => f.value = !0),
          onBlur: A[1] || (A[1] = (R) => f.value = !1),
          onPointerup: y,
          onPointerdown: A[2] || (A[2] = (R) => {
            R.currentTarget.focus({ preventScroll: !0 });
          }),
          onTouchend: A[3] || (A[3] = Et(() => {
          }, ["prevent", "stop"])),
          onPointermove: v,
          onPointerleave: w,
          onKeydown: m
        }, {
          default: S(() => [
            P(x.$slots, "default")
          ]),
          _: 3
        }, 8, ["aria-labelledby", "data-highlighted", "aria-selected", "data-state", "aria-disabled", "data-disabled", "tabindex", "as", "as-child"])
      ]),
      _: 3
    }, 8, ["value"]));
  }
}), Dv = /* @__PURE__ */ T({
  __name: "SelectItemIndicator",
  props: {
    asChild: { type: Boolean },
    as: { default: "span" }
  },
  setup(e) {
    const t = e, n = Ju();
    return (o, r) => d(n).isSelected.value ? (_(), E(d(ie), z({
      key: 0,
      "aria-hidden": "true"
    }, t), {
      default: S(() => [
        P(o.$slots, "default")
      ]),
      _: 3
    }, 16)) : Be("", !0);
  }
}), [Rv, Iv] = Ve("SelectGroup"), Lv = /* @__PURE__ */ T({
  __name: "SelectGroup",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e, n = yt(void 0, "reka-select-group");
    return Iv({ id: n }), (o, r) => (_(), E(d(ie), z({ role: "group" }, t, { "aria-labelledby": d(n) }), {
      default: S(() => [
        P(o.$slots, "default")
      ]),
      _: 3
    }, 16, ["aria-labelledby"]));
  }
}), Fv = /* @__PURE__ */ T({
  __name: "SelectLabel",
  props: {
    for: {},
    asChild: { type: Boolean },
    as: { default: "div" }
  },
  setup(e) {
    const t = e, n = Rv({ id: "" });
    return (o, r) => (_(), E(d(ie), z(t, {
      id: d(n).id
    }), {
      default: S(() => [
        P(o.$slots, "default")
      ]),
      _: 3
    }, 16, ["id"]));
  }
}), Zu = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "SelectItemText",
  props: {
    asChild: { type: Boolean },
    as: { default: "span" }
  },
  setup(e) {
    const t = e, n = Bn(), o = kn(), r = Ju(), { forwardRef: s, currentElement: a } = X(), i = O(() => {
      var l, u;
      return {
        value: r.value,
        disabled: r.disabled.value,
        textContent: ((l = a.value) == null ? void 0 : l.textContent) ?? ((u = r.value) == null ? void 0 : u.toString()) ?? ""
      };
    });
    return Se(() => {
      a.value && (r.onItemTextChange(a.value), o.itemTextRefCallback(
        a.value,
        r.value,
        r.disabled.value
      ), n.onOptionAdd(i.value));
    }), Ao(() => {
      n.onOptionRemove(i.value);
    }), (l, u) => (_(), E(d(ie), z({
      id: d(r).textId,
      ref: d(s)
    }, { ...t, ...l.$attrs }), {
      default: S(() => [
        P(l.$slots, "default")
      ]),
      _: 3
    }, 16, ["id"]));
  }
}), Vv = /* @__PURE__ */ T({
  __name: "SelectViewport",
  props: {
    nonce: {},
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e, { nonce: n } = Xe(t), o = Ru(n), r = kn(), s = r.position === "item-aligned" ? _a() : void 0, { forwardRef: a, currentElement: i } = X();
    Se(() => {
      r == null || r.onViewportChange(i.value);
    });
    const l = B(0);
    function u(c) {
      const f = c.currentTarget, { shouldExpandOnScrollRef: p, contentWrapper: h } = s ?? {};
      if (p != null && p.value && (h != null && h.value)) {
        const g = Math.abs(l.value - f.scrollTop);
        if (g > 0) {
          const y = window.innerHeight - Tt * 2, C = Number.parseFloat(
            h.value.style.minHeight
          ), v = Number.parseFloat(h.value.style.height), w = Math.max(C, v);
          if (w < y) {
            const m = w + g, x = Math.min(y, m), A = m - x;
            h.value.style.height = `${x}px`, h.value.style.bottom === "0px" && (f.scrollTop = A > 0 ? A : 0, h.value.style.justifyContent = "flex-end");
          }
        }
      }
      l.value = f.scrollTop;
    }
    return (c, f) => (_(), oe(ke, null, [
      W(d(ie), z({
        ref: d(a),
        "data-reka-select-viewport": "",
        role: "presentation"
      }, { ...c.$attrs, ...t }, {
        style: {
          // we use position: 'relative' here on the `viewport` so that when we call
          // `selectedItem.offsetTop` in calculations, the offset is relative to the viewport
          // (independent of the scrollUpButton).
          position: "relative",
          flex: 1,
          overflow: "hidden auto"
        },
        onScroll: u
      }), {
        default: S(() => [
          P(c.$slots, "default")
        ]),
        _: 3
      }, 16),
      W(d(ie), {
        as: "style",
        nonce: d(o)
      }, {
        default: S(() => f[0] || (f[0] = [
          At(" /* Hide scrollbars cross-browser and enable momentum scroll for touch devices */ [data-reka-select-viewport] { scrollbar-width:none; -ms-overflow-style: none; -webkit-overflow-scrolling: touch; } [data-reka-select-viewport]::-webkit-scrollbar { display: none; } ")
        ])),
        _: 1
      }, 8, ["nonce"])
    ], 64));
  }
}), Qu = /* @__PURE__ */ T({
  __name: "SelectScrollButtonImpl",
  emits: ["autoScroll"],
  setup(e, { emit: t }) {
    const n = t, { getItems: o } = rn(), r = kn(), s = B(null);
    function a() {
      s.value !== null && (window.clearInterval(s.value), s.value = null);
    }
    Me(() => {
      const u = o().map((c) => c.ref).find(
        (c) => c === ot()
      );
      u == null || u.scrollIntoView({ block: "nearest" });
    });
    function i() {
      s.value === null && (s.value = window.setInterval(() => {
        n("autoScroll");
      }, 50));
    }
    function l() {
      var u;
      (u = r.onItemLeave) == null || u.call(r), s.value === null && (s.value = window.setInterval(() => {
        n("autoScroll");
      }, 50));
    }
    return Ao(() => a()), (u, c) => {
      var f;
      return _(), E(d(ie), z({
        "aria-hidden": "true",
        style: {
          flexShrink: 0
        }
      }, (f = u.$parent) == null ? void 0 : f.$props, {
        onPointerdown: i,
        onPointermove: l,
        onPointerleave: c[0] || (c[0] = () => {
          a();
        })
      }), {
        default: S(() => [
          P(u.$slots, "default")
        ]),
        _: 3
      }, 16);
    };
  }
}), Nv = /* @__PURE__ */ T({
  __name: "SelectScrollUpButton",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = kn(), n = t.position === "item-aligned" ? _a() : void 0, { forwardRef: o, currentElement: r } = X(), s = B(!1);
    return Me((a) => {
      var i, l;
      if ((i = t.viewport) != null && i.value && ((l = t.isPositioned) != null && l.value)) {
        let u = function() {
          s.value = c.scrollTop > 0;
        };
        const c = t.viewport.value;
        u(), c.addEventListener("scroll", u), a(() => c.removeEventListener("scroll", u));
      }
    }), be(r, () => {
      r.value && (n == null || n.onScrollButtonChange(r.value));
    }), (a, i) => s.value ? (_(), E(Qu, {
      key: 0,
      ref: d(o),
      onAutoScroll: i[0] || (i[0] = () => {
        const { viewport: l, selectedItem: u } = d(t);
        l != null && l.value && (u != null && u.value) && (l.value.scrollTop = l.value.scrollTop - u.value.offsetHeight);
      })
    }, {
      default: S(() => [
        P(a.$slots, "default")
      ]),
      _: 3
    }, 512)) : Be("", !0);
  }
}), zv = /* @__PURE__ */ T({
  __name: "SelectScrollDownButton",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = kn(), n = t.position === "item-aligned" ? _a() : void 0, { forwardRef: o, currentElement: r } = X(), s = B(!1);
    return Me((a) => {
      var i, l;
      if ((i = t.viewport) != null && i.value && ((l = t.isPositioned) != null && l.value)) {
        let u = function() {
          const f = c.scrollHeight - c.clientHeight;
          s.value = Math.ceil(c.scrollTop) < f;
        };
        const c = t.viewport.value;
        u(), c.addEventListener("scroll", u), a(() => c.removeEventListener("scroll", u));
      }
    }), be(r, () => {
      r.value && (n == null || n.onScrollButtonChange(r.value));
    }), (a, i) => s.value ? (_(), E(Qu, {
      key: 0,
      ref: d(o),
      onAutoScroll: i[0] || (i[0] = () => {
        const { viewport: l, selectedItem: u } = d(t);
        l != null && l.value && (u != null && u.value) && (l.value.scrollTop = l.value.scrollTop + u.value.offsetHeight);
      })
    }, {
      default: S(() => [
        P(a.$slots, "default")
      ]),
      _: 3
    }, 512)) : Be("", !0);
  }
}), jv = /* @__PURE__ */ T({
  __name: "SelectValue",
  props: {
    placeholder: { default: "" },
    asChild: { type: Boolean },
    as: { default: "span" }
  },
  setup(e) {
    const t = e, { forwardRef: n, currentElement: o } = X(), r = Bn();
    Se(() => {
      r.valueElement = o;
    });
    const s = O(() => {
      var c;
      let i = [];
      const l = Array.from(r.optionsSet.value), u = (f) => l.find((p) => p.value === f);
      return Array.isArray(r.modelValue.value) ? i = r.modelValue.value.map((f) => {
        var p;
        return ((p = u(f)) == null ? void 0 : p.textContent) ?? "";
      }) : i = [((c = u(r.modelValue.value)) == null ? void 0 : c.textContent) ?? ""], i.filter(Boolean);
    }), a = O(() => s.value.length ? s.value.join(", ") : t.placeholder);
    return (i, l) => (_(), E(d(ie), {
      ref: d(n),
      as: i.as,
      "as-child": i.asChild,
      style: { pointerEvents: "none" },
      "data-placeholder": s.value.length ? void 0 : t.placeholder
    }, {
      default: S(() => [
        P(i.$slots, "default", {
          selectedLabel: s.value,
          modelValue: d(r).modelValue.value
        }, () => [
          At(xt(a.value), 1)
        ])
      ]),
      _: 3
    }, 8, ["as", "as-child", "data-placeholder"]));
  }
}), Hv = /* @__PURE__ */ T({
  __name: "SelectIcon",
  props: {
    asChild: { type: Boolean },
    as: { default: "span" }
  },
  setup(e) {
    return (t, n) => (_(), E(d(ie), {
      "aria-hidden": "true",
      as: t.as,
      "as-child": t.asChild
    }, {
      default: S(() => [
        P(t.$slots, "default", {}, () => [
          n[0] || (n[0] = At("▼"))
        ])
      ]),
      _: 3
    }, 8, ["as", "as-child"]));
  }
}), [Wv, Kv] = Ve("SwitchRoot"), Uv = /* @__PURE__ */ T({
  __name: "SwitchRoot",
  props: {
    defaultValue: { type: Boolean },
    modelValue: { type: [Boolean, null], default: void 0 },
    disabled: { type: Boolean },
    id: {},
    value: { default: "on" },
    asChild: { type: Boolean },
    as: { default: "button" },
    name: {},
    required: { type: Boolean }
  },
  emits: ["update:modelValue"],
  setup(e, { emit: t }) {
    const n = e, o = t, { disabled: r } = Xe(n), s = vt(n, "modelValue", o, {
      defaultValue: n.defaultValue,
      passive: n.modelValue === void 0
    });
    function a() {
      r.value || (s.value = !s.value);
    }
    const { forwardRef: i, currentElement: l } = X(), u = ku(l), c = O(() => {
      var f;
      return n.id && l.value ? (f = document.querySelector(`[for="${n.id}"]`)) == null ? void 0 : f.innerText : void 0;
    });
    return Kv({
      modelValue: s,
      toggleCheck: a,
      disabled: r
    }), (f, p) => (_(), E(d(ie), z(f.$attrs, {
      id: f.id,
      ref: d(i),
      role: "switch",
      type: f.as === "button" ? "button" : void 0,
      value: f.value,
      "aria-label": f.$attrs["aria-label"] || c.value,
      "aria-checked": d(s),
      "aria-required": f.required,
      "data-state": d(s) ? "checked" : "unchecked",
      "data-disabled": d(r) ? "" : void 0,
      "as-child": f.asChild,
      as: f.as,
      disabled: d(r),
      onClick: a,
      onKeydown: Xs(Et(a, ["prevent"]), ["enter"])
    }), {
      default: S(() => [
        P(f.$slots, "default", { modelValue: d(s) }),
        d(u) && f.name ? (_(), E(d(eg), {
          key: 0,
          type: "checkbox",
          name: f.name,
          disabled: d(r),
          required: f.required,
          value: f.value,
          checked: !!d(s)
        }, null, 8, ["name", "disabled", "required", "value", "checked"])) : Be("", !0)
      ]),
      _: 3
    }, 16, ["id", "type", "value", "aria-label", "aria-checked", "aria-required", "data-state", "data-disabled", "as-child", "as", "disabled", "onKeydown"]));
  }
}), Gv = /* @__PURE__ */ T({
  __name: "SwitchThumb",
  props: {
    asChild: { type: Boolean },
    as: { default: "span" }
  },
  setup(e) {
    const t = Wv();
    return X(), (n, o) => {
      var r;
      return _(), E(d(ie), {
        "data-state": (r = d(t).modelValue) != null && r.value ? "checked" : "unchecked",
        "data-disabled": d(t).disabled.value ? "" : void 0,
        "as-child": n.asChild,
        as: n.as
      }, {
        default: S(() => [
          P(n.$slots, "default")
        ]),
        _: 3
      }, 8, ["data-state", "data-disabled", "as-child", "as"]);
    };
  }
}), [xa, Yv] = Ve("TabsRoot"), qv = /* @__PURE__ */ T({
  __name: "TabsRoot",
  props: {
    defaultValue: {},
    orientation: { default: "horizontal" },
    dir: {},
    activationMode: { default: "automatic" },
    modelValue: {},
    unmountOnHide: { type: Boolean, default: !0 },
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["update:modelValue"],
  setup(e, { emit: t }) {
    const n = e, o = t, { orientation: r, unmountOnHide: s, dir: a } = Xe(n), i = to(a);
    X();
    const l = vt(n, "modelValue", o, {
      defaultValue: n.defaultValue,
      passive: n.modelValue === void 0
    }), u = B();
    return Yv({
      modelValue: l,
      changeModelValue: (c) => {
        l.value = c;
      },
      orientation: r,
      dir: i,
      unmountOnHide: s,
      activationMode: n.activationMode,
      baseId: yt(void 0, "reka-tabs"),
      tabsList: u
    }), (c, f) => (_(), E(d(ie), {
      dir: d(i),
      "data-orientation": d(r),
      "as-child": c.asChild,
      as: c.as
    }, {
      default: S(() => [
        P(c.$slots, "default", { modelValue: d(l) })
      ]),
      _: 3
    }, 8, ["dir", "data-orientation", "as-child", "as"]));
  }
}), Xv = /* @__PURE__ */ T({
  __name: "TabsList",
  props: {
    loop: { type: Boolean, default: !0 },
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e, { loop: n } = Xe(t), { forwardRef: o, currentElement: r } = X(), s = xa();
    return s.tabsList = r, (a, i) => (_(), E(d(Bu), {
      "as-child": "",
      orientation: d(s).orientation.value,
      dir: d(s).dir.value,
      loop: d(n)
    }, {
      default: S(() => [
        W(d(ie), {
          ref: d(o),
          role: "tablist",
          "as-child": a.asChild,
          as: a.as,
          "aria-orientation": d(s).orientation.value
        }, {
          default: S(() => [
            P(a.$slots, "default")
          ]),
          _: 3
        }, 8, ["as-child", "as", "aria-orientation"])
      ]),
      _: 3
    }, 8, ["orientation", "dir", "loop"]));
  }
});
function ec(e, t) {
  return `${e}-trigger-${t}`;
}
function tc(e, t) {
  return `${e}-content-${t}`;
}
const Jv = /* @__PURE__ */ T({
  __name: "TabsContent",
  props: {
    value: {},
    forceMount: { type: Boolean },
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e, { forwardRef: n } = X(), o = xa(), r = O(() => ec(o.baseId, t.value)), s = O(() => tc(o.baseId, t.value)), a = O(() => t.value === o.modelValue.value), i = B(a.value);
    return Se(() => {
      requestAnimationFrame(() => {
        i.value = !1;
      });
    }), (l, u) => (_(), E(d(kt), {
      present: l.forceMount || a.value,
      "force-mount": ""
    }, {
      default: S(({ present: c }) => [
        W(d(ie), {
          id: s.value,
          ref: d(n),
          "as-child": l.asChild,
          as: l.as,
          role: "tabpanel",
          "data-state": a.value ? "active" : "inactive",
          "data-orientation": d(o).orientation.value,
          "aria-labelledby": r.value,
          hidden: !c,
          tabindex: "0",
          style: nt({
            animationDuration: i.value ? "0s" : void 0
          })
        }, {
          default: S(() => [
            !d(o).unmountOnHide.value || c ? P(l.$slots, "default", { key: 0 }) : Be("", !0)
          ]),
          _: 2
        }, 1032, ["id", "as-child", "as", "data-state", "data-orientation", "aria-labelledby", "hidden", "style"])
      ]),
      _: 3
    }, 8, ["present"]));
  }
}), Zv = /* @__PURE__ */ T({
  __name: "TabsTrigger",
  props: {
    value: {},
    disabled: { type: Boolean, default: !1 },
    asChild: { type: Boolean },
    as: { default: "button" }
  },
  setup(e) {
    const t = e, { forwardRef: n } = X(), o = xa(), r = O(() => ec(o.baseId, t.value)), s = O(() => tc(o.baseId, t.value)), a = O(() => t.value === o.modelValue.value);
    return (i, l) => (_(), E(d(tg), {
      "as-child": "",
      focusable: !i.disabled,
      active: a.value
    }, {
      default: S(() => [
        W(d(ie), {
          id: r.value,
          ref: d(n),
          role: "tab",
          type: i.as === "button" ? "button" : void 0,
          as: i.as,
          "as-child": i.asChild,
          "aria-selected": a.value ? "true" : "false",
          "aria-controls": s.value,
          "data-state": a.value ? "active" : "inactive",
          disabled: i.disabled,
          "data-disabled": i.disabled ? "" : void 0,
          "data-orientation": d(o).orientation.value,
          onMousedown: l[0] || (l[0] = Et((u) => {
            !i.disabled && u.ctrlKey === !1 ? d(o).changeModelValue(i.value) : u.preventDefault();
          }, ["left"])),
          onKeydown: l[1] || (l[1] = Xs((u) => d(o).changeModelValue(i.value), ["enter", "space"])),
          onFocus: l[2] || (l[2] = () => {
            const u = d(o).activationMode !== "manual";
            !a.value && !i.disabled && u && d(o).changeModelValue(i.value);
          })
        }, {
          default: S(() => [
            P(i.$slots, "default")
          ]),
          _: 3
        }, 8, ["id", "type", "as", "as-child", "aria-selected", "aria-controls", "data-state", "disabled", "data-disabled", "data-orientation"])
      ]),
      _: 3
    }, 8, ["focusable", "active"]));
  }
}), nc = "tooltip.open", [Ca, Qv] = Ve("TooltipProvider"), ey = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "TooltipProvider",
  props: {
    delayDuration: { default: 700 },
    skipDelayDuration: { default: 300 },
    disableHoverableContent: { type: Boolean, default: !1 },
    disableClosingTrigger: { type: Boolean },
    disabled: { type: Boolean },
    ignoreNonKeyboardFocus: { type: Boolean, default: !1 }
  },
  setup(e) {
    const t = e, { delayDuration: n, skipDelayDuration: o, disableHoverableContent: r, disableClosingTrigger: s, ignoreNonKeyboardFocus: a, disabled: i } = Xe(t);
    X();
    const l = B(!0), u = B(!1), { start: c, stop: f } = bu(() => {
      l.value = !0;
    }, o, { immediate: !1 });
    return Qv({
      isOpenDelayed: l,
      delayDuration: n,
      onOpen() {
        f(), l.value = !1;
      },
      onClose() {
        c();
      },
      isPointerInTransitRef: u,
      disableHoverableContent: r,
      disableClosingTrigger: s,
      disabled: i,
      ignoreNonKeyboardFocus: a
    }), (p, h) => P(p.$slots, "default");
  }
}), [Fr, ty] = Ve("TooltipRoot"), ny = /* @__PURE__ */ T({
  __name: "TooltipRoot",
  props: {
    defaultOpen: { type: Boolean, default: !1 },
    open: { type: Boolean, default: void 0 },
    delayDuration: { default: void 0 },
    disableHoverableContent: { type: Boolean, default: void 0 },
    disableClosingTrigger: { type: Boolean, default: void 0 },
    disabled: { type: Boolean, default: void 0 },
    ignoreNonKeyboardFocus: { type: Boolean, default: void 0 }
  },
  emits: ["update:open"],
  setup(e, { emit: t }) {
    const n = e, o = t;
    X();
    const r = Ca(), s = O(() => n.disableHoverableContent ?? r.disableHoverableContent.value), a = O(() => n.disableClosingTrigger ?? r.disableClosingTrigger.value), i = O(() => n.disabled ?? r.disabled.value), l = O(() => n.delayDuration ?? r.delayDuration.value), u = O(() => n.ignoreNonKeyboardFocus ?? r.ignoreNonKeyboardFocus.value), c = vt(n, "open", o, {
      defaultValue: n.defaultOpen,
      passive: n.open === void 0
    });
    be(c, (m) => {
      r.onClose && (m ? (r.onOpen(), document.dispatchEvent(new CustomEvent(nc))) : r.onClose());
    });
    const f = B(!1), p = B(), h = O(() => c.value ? f.value ? "delayed-open" : "instant-open" : "closed"), { start: g, stop: y } = bu(() => {
      f.value = !0, c.value = !0;
    }, l, { immediate: !1 });
    function C() {
      y(), f.value = !1, c.value = !0;
    }
    function v() {
      y(), c.value = !1;
    }
    function w() {
      g();
    }
    return ty({
      contentId: "",
      open: c,
      stateAttribute: h,
      trigger: p,
      onTriggerChange(m) {
        p.value = m;
      },
      onTriggerEnter() {
        r.isOpenDelayed.value ? w() : C();
      },
      onTriggerLeave() {
        s.value ? v() : y();
      },
      onOpen: C,
      onClose: v,
      disableHoverableContent: s,
      disableClosingTrigger: a,
      disabled: i,
      ignoreNonKeyboardFocus: u
    }), (m, x) => (_(), E(d(Dr), null, {
      default: S(() => [
        P(m.$slots, "default", { open: d(c) })
      ]),
      _: 3
    }));
  }
}), oy = /* @__PURE__ */ T({
  __name: "TooltipTrigger",
  props: {
    reference: {},
    asChild: { type: Boolean },
    as: { default: "button" }
  },
  setup(e) {
    const t = e, n = Fr(), o = Ca();
    n.contentId || (n.contentId = yt(void 0, "reka-tooltip-content"));
    const { forwardRef: r, currentElement: s } = X(), a = B(!1), i = B(!1), l = O(() => n.disabled.value ? {} : {
      click: y,
      focus: h,
      pointermove: f,
      pointerleave: p,
      pointerdown: c,
      blur: g
    });
    Se(() => {
      n.onTriggerChange(s.value);
    });
    function u() {
      setTimeout(() => {
        a.value = !1;
      }, 1);
    }
    function c() {
      a.value = !0, document.addEventListener("pointerup", u, { once: !0 });
    }
    function f(C) {
      C.pointerType !== "touch" && !i.value && !o.isPointerInTransitRef.value && (n.onTriggerEnter(), i.value = !0);
    }
    function p() {
      n.onTriggerLeave(), i.value = !1;
    }
    function h(C) {
      var v, w;
      a.value || n.ignoreNonKeyboardFocus.value && !((w = (v = C.target).matches) != null && w.call(v, ":focus-visible")) || n.onOpen();
    }
    function g() {
      n.onClose();
    }
    function y() {
      n.disableClosingTrigger.value || n.onClose();
    }
    return (C, v) => (_(), E(d(ma), {
      "as-child": "",
      reference: C.reference
    }, {
      default: S(() => [
        W(d(ie), z({
          ref: d(r),
          "aria-describedby": d(n).open.value ? d(n).contentId : void 0,
          "data-state": d(n).stateAttribute.value,
          as: C.as,
          "as-child": t.asChild,
          "data-grace-area-trigger": ""
        }, Pd(l.value)), {
          default: S(() => [
            P(C.$slots, "default")
          ]),
          _: 3
        }, 16, ["aria-describedby", "data-state", "as", "as-child"])
      ]),
      _: 3
    }, 8, ["reference"]));
  }
}), oc = /* @__PURE__ */ T({
  __name: "TooltipContentImpl",
  props: {
    ariaLabel: {},
    asChild: { type: Boolean },
    as: {},
    side: { default: "top" },
    sideOffset: { default: 0 },
    align: { default: "center" },
    alignOffset: {},
    avoidCollisions: { type: Boolean, default: !0 },
    collisionBoundary: { default: () => [] },
    collisionPadding: { default: 0 },
    arrowPadding: { default: 0 },
    sticky: { default: "partial" },
    hideWhenDetached: { type: Boolean, default: !1 },
    positionStrategy: {},
    updatePositionStrategy: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = Fr(), { forwardRef: s } = X(), a = Ad(), i = O(() => {
      var c;
      return (c = a.default) == null ? void 0 : c.call(a, {});
    }), l = O(() => {
      var p;
      if (n.ariaLabel)
        return n.ariaLabel;
      let c = "";
      function f(h) {
        typeof h.children == "string" && h.type !== Vt ? c += h.children : Array.isArray(h.children) && h.children.forEach((g) => f(g));
      }
      return (p = i.value) == null || p.forEach((h) => f(h)), c;
    }), u = O(() => {
      const { ariaLabel: c, ...f } = n;
      return f;
    });
    return Se(() => {
      Xn(window, "scroll", (c) => {
        const f = c.target;
        f != null && f.contains(r.trigger.value) && r.onClose();
      }), Xn(window, nc, r.onClose);
    }), (c, f) => (_(), E(d(Mr), {
      "as-child": "",
      "disable-outside-pointer-events": !1,
      onEscapeKeyDown: f[0] || (f[0] = (p) => o("escapeKeyDown", p)),
      onPointerDownOutside: f[1] || (f[1] = (p) => {
        var h;
        d(r).disableClosingTrigger.value && ((h = d(r).trigger.value) != null && h.contains(p.target)) && p.preventDefault(), o("pointerDownOutside", p);
      }),
      onFocusOutside: f[2] || (f[2] = Et(() => {
      }, ["prevent"])),
      onDismiss: f[3] || (f[3] = (p) => d(r).onClose())
    }, {
      default: S(() => [
        W(d(ga), z({
          ref: d(s),
          "data-state": d(r).stateAttribute.value
        }, { ...c.$attrs, ...u.value }, { style: {
          "--reka-tooltip-content-transform-origin": "var(--reka-popper-transform-origin)",
          "--reka-tooltip-content-available-width": "var(--reka-popper-available-width)",
          "--reka-tooltip-content-available-height": "var(--reka-popper-available-height)",
          "--reka-tooltip-trigger-width": "var(--reka-popper-anchor-width)",
          "--reka-tooltip-trigger-height": "var(--reka-popper-anchor-height)"
        } }), {
          default: S(() => [
            P(c.$slots, "default"),
            W(d(ia), {
              id: d(r).contentId,
              role: "tooltip"
            }, {
              default: S(() => [
                At(xt(l.value), 1)
              ]),
              _: 1
            }, 8, ["id"])
          ]),
          _: 3
        }, 16, ["data-state"])
      ]),
      _: 3
    }));
  }
}), ry = /* @__PURE__ */ T({
  __name: "TooltipContentHoverable",
  props: {
    ariaLabel: {},
    asChild: { type: Boolean },
    as: {},
    side: {},
    sideOffset: {},
    align: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {}
  },
  setup(e) {
    const n = wt(e), { forwardRef: o, currentElement: r } = X(), { trigger: s, onClose: a } = Fr(), i = Ca(), { isPointerInTransit: l, onPointerExit: u } = Gg(s, r);
    return i.isPointerInTransitRef = l, u(() => {
      a();
    }), (c, f) => (_(), E(oc, z({ ref: d(o) }, d(n)), {
      default: S(() => [
        P(c.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), sy = /* @__PURE__ */ T({
  __name: "TooltipContent",
  props: {
    forceMount: { type: Boolean },
    ariaLabel: {},
    asChild: { type: Boolean },
    as: {},
    side: { default: "top" },
    sideOffset: {},
    align: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = Fr(), s = Ne(n, o), { forwardRef: a } = X();
    return (i, l) => (_(), E(d(kt), {
      present: i.forceMount || d(r).open.value
    }, {
      default: S(() => [
        (_(), E(_t(d(r).disableHoverableContent.value ? oc : ry), z({ ref: d(a) }, d(s)), {
          default: S(() => [
            P(i.$slots, "default")
          ]),
          _: 3
        }, 16))
      ]),
      _: 3
    }, 8, ["present"]));
  }
}), ay = /* @__PURE__ */ T({
  __name: "TooltipPortal",
  props: {
    to: {},
    disabled: { type: Boolean },
    defer: { type: Boolean },
    forceMount: { type: Boolean }
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(kr), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), iy = /* @__PURE__ */ T({
  __name: "DropdownMenu",
  props: {
    defaultOpen: { type: Boolean },
    open: { type: Boolean },
    dir: {},
    modal: { type: Boolean }
  },
  emits: ["update:open"],
  setup(e, { emit: t }) {
    const r = Ne(e, t);
    return (s, a) => (_(), E(d(Mg), me(we(d(r))), {
      default: S(() => [
        P(s.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
});
/**
 * @license lucide-vue-next v0.487.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */
const ki = (e) => e.replace(/([a-z0-9])([A-Z])/g, "$1-$2").toLowerCase(), ly = (e) => e.replace(
  /^([A-Z])|[\s-_]+(\w)/g,
  (t, n, o) => o ? o.toUpperCase() : n.toLowerCase()
), uy = (e) => {
  const t = ly(e);
  return t.charAt(0).toUpperCase() + t.slice(1);
}, cy = (...e) => e.filter((t, n, o) => !!t && t.trim() !== "" && o.indexOf(t) === n).join(" ").trim();
/**
 * @license lucide-vue-next v0.487.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */
var Wo = {
  xmlns: "http://www.w3.org/2000/svg",
  width: 24,
  height: 24,
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  "stroke-width": 2,
  "stroke-linecap": "round",
  "stroke-linejoin": "round"
};
/**
 * @license lucide-vue-next v0.487.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */
const dy = ({ size: e, strokeWidth: t = 2, absoluteStrokeWidth: n, color: o, iconNode: r, name: s, class: a, ...i }, { slots: l }) => St(
  "svg",
  {
    ...Wo,
    width: e || Wo.width,
    height: e || Wo.height,
    stroke: o || Wo.stroke,
    "stroke-width": n ? Number(t) * 24 / Number(e) : t,
    class: cy(
      "lucide",
      ...s ? [`lucide-${ki(uy(s))}-icon`, `lucide-${ki(s)}`] : ["lucide-icon"]
    ),
    ...i
  },
  [...r.map((u) => St(...u)), ...l.default ? [l.default()] : []]
);
/**
 * @license lucide-vue-next v0.487.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */
const no = (e, t) => (n, { slots: o }) => St(
  dy,
  {
    ...n,
    iconNode: t,
    name: e
  },
  o
);
/**
 * @license lucide-vue-next v0.487.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */
const rc = no("check", [["path", { d: "M20 6 9 17l-5-5", key: "1gmf2c" }]]);
/**
 * @license lucide-vue-next v0.487.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */
const sc = no("chevron-down", [
  ["path", { d: "m6 9 6 6 6-6", key: "qrunsl" }]
]);
/**
 * @license lucide-vue-next v0.487.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */
const fy = no("chevron-right", [
  ["path", { d: "m9 18 6-6-6-6", key: "mthhwq" }]
]);
/**
 * @license lucide-vue-next v0.487.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */
const py = no("chevron-up", [
  ["path", { d: "m18 15-6-6-6 6", key: "153udz" }]
]);
/**
 * @license lucide-vue-next v0.487.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */
const hy = no("circle", [
  ["circle", { cx: "12", cy: "12", r: "10", key: "1mglay" }]
]);
/**
 * @license lucide-vue-next v0.487.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */
const my = no("x", [
  ["path", { d: "M18 6 6 18", key: "1bl5f8" }],
  ["path", { d: "m6 6 12 12", key: "d8bk6v" }]
]), gy = { class: "absolute left-2 flex h-3.5 w-3.5 items-center justify-center" }, vy = /* @__PURE__ */ T({
  __name: "DropdownMenuCheckboxItem",
  props: {
    modelValue: { type: [Boolean, String] },
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  emits: ["select", "update:modelValue"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = O(() => {
      const { class: a, ...i } = n;
      return i;
    }), s = Ne(r, o);
    return (a, i) => (_(), E(d(Ng), z(d(s), {
      class: d(fe)(
        "relative flex cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none transition-colors focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50",
        n.class
      )
    }), {
      default: S(() => [
        Z("span", gy, [
          W(d(Wu), null, {
            default: S(() => [
              W(d(rc), { class: "w-4 h-4" })
            ]),
            _: 1
          })
        ]),
        P(a.$slots, "default")
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), Vr = () => {
  const e = B("#modals"), t = () => {
    const n = document.querySelector("unraid-modals");
    if (!(n != null && n.shadowRoot)) return;
    const o = n.shadowRoot.querySelector("#modals");
    o && (e.value = o, console.log("[determineTeleportTarget] teleportTarget", e.value));
  };
  return Se(() => {
    t();
  }), {
    teleportTarget: e,
    determineTeleportTarget: t
  };
}, yy = { class: "overflow-hidden" }, by = /* @__PURE__ */ T({
  __name: "DropdownMenuContent",
  props: {
    forceMount: { type: Boolean },
    loop: { type: Boolean },
    side: {},
    sideOffset: { default: 4 },
    align: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    disableUpdateOnLayoutShift: { type: Boolean },
    prioritizePosition: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "closeAutoFocus"],
  setup(e, { emit: t }) {
    const { teleportTarget: n } = Vr(), o = e, r = t, s = O(() => {
      const { class: i, ...l } = o;
      return l;
    }), a = Ne(s, r);
    return (i, l) => (_(), E(d(Rg), { to: d(n) }, {
      default: S(() => [
        W(d(Ig), z(d(a), {
          side: "bottom",
          class: d(fe)(
            "z-50 min-w-32 rounded-lg bg-popover p-1 text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2",
            o.class
          )
        }), {
          default: S(() => [
            Z("div", yy, [
              P(i.$slots, "default")
            ])
          ]),
          _: 3
        }, 16, ["class"])
      ]),
      _: 3
    }, 8, ["to"]));
  }
}), wy = /* @__PURE__ */ T({
  __name: "DropdownMenuGroup",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(Fg), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), _y = /* @__PURE__ */ T({
  __name: "DropdownMenuItem",
  props: {
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {},
    class: {},
    inset: { type: Boolean }
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: r, ...s } = t;
      return s;
    }), o = wt(n);
    return (r, s) => (_(), E(d(Lg), z(d(o), {
      class: d(fe)(
        "relative flex cursor-default select-none items-center rounded-sm gap-2 px-2 py-1.5 text-sm outline-none transition-colors focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&>svg]:size-4 [&>svg]:shrink-0",
        r.inset && "pl-8",
        t.class
      )
    }), {
      default: S(() => [
        P(r.$slots, "default")
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), xy = /* @__PURE__ */ T({
  __name: "DropdownMenuLabel",
  props: {
    asChild: { type: Boolean },
    as: {},
    class: {},
    inset: { type: Boolean }
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: r, ...s } = t;
      return s;
    }), o = wt(n);
    return (r, s) => (_(), E(d(zg), z(d(o), {
      class: d(fe)("px-2 py-1.5 text-sm font-semibold", r.inset && "pl-8", t.class)
    }), {
      default: S(() => [
        P(r.$slots, "default")
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), Cy = /* @__PURE__ */ T({
  __name: "DropdownMenuRadioGroup",
  props: {
    modelValue: {},
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["update:modelValue"],
  setup(e, { emit: t }) {
    const r = Ne(e, t);
    return (s, a) => (_(), E(d(jg), me(we(d(r))), {
      default: S(() => [
        P(s.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Sy = { class: "absolute left-2 flex h-3.5 w-3.5 items-center justify-center" }, $y = /* @__PURE__ */ T({
  __name: "DropdownMenuRadioItem",
  props: {
    value: {},
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  emits: ["select"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = O(() => {
      const { class: a, ...i } = n;
      return i;
    }), s = Ne(r, o);
    return (a, i) => (_(), E(d(Hg), z(d(s), {
      class: d(fe)(
        "relative flex cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none transition-colors focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50",
        n.class
      )
    }), {
      default: S(() => [
        Z("span", Sy, [
          W(d(Wu), null, {
            default: S(() => [
              W(d(hy), { class: "h-2 w-2 fill-current" })
            ]),
            _: 1
          })
        ]),
        P(a.$slots, "default")
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), Ty = /* @__PURE__ */ T({
  __name: "DropdownMenuSeparator",
  props: {
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: o, ...r } = t;
      return r;
    });
    return (o, r) => (_(), E(d(Vg), z(n.value, {
      class: d(fe)("-mx-1 my-1 h-px bg-muted", t.class)
    }), null, 16, ["class"]));
  }
}), Py = /* @__PURE__ */ T({
  __name: "DropdownMenuShortcut",
  props: {
    class: {}
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), oe("span", {
      class: le(d(fe)("ml-auto text-xs tracking-widest opacity-60", t.class))
    }, [
      P(n.$slots, "default")
    ], 2));
  }
}), Ey = /* @__PURE__ */ T({
  __name: "DropdownMenuSub",
  props: {
    defaultOpen: { type: Boolean },
    open: { type: Boolean }
  },
  emits: ["update:open"],
  setup(e, { emit: t }) {
    const r = Ne(e, t);
    return (s, a) => (_(), E(d(Wg), me(we(d(r))), {
      default: S(() => [
        P(s.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Ay = /* @__PURE__ */ T({
  __name: "DropdownMenuSubContent",
  props: {
    forceMount: { type: Boolean },
    loop: { type: Boolean },
    sideOffset: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    disableUpdateOnLayoutShift: { type: Boolean },
    prioritizePosition: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "entryFocus", "openAutoFocus", "closeAutoFocus"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = O(() => {
      const { class: a, ...i } = n;
      return i;
    }), s = Ne(r, o);
    return (a, i) => (_(), E(d(Kg), z(d(s), {
      class: d(fe)(
        "z-50 min-w-32 overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2",
        n.class
      )
    }), {
      default: S(() => [
        P(a.$slots, "default")
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), Oy = /* @__PURE__ */ T({
  __name: "DropdownMenuSubTrigger",
  props: {
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: r, ...s } = t;
      return s;
    }), o = wt(n);
    return (r, s) => (_(), E(d(Ug), z(d(o), {
      class: d(fe)(
        "flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none focus:bg-accent data-[state=open]:bg-accent",
        t.class
      )
    }), {
      default: S(() => [
        P(r.$slots, "default"),
        W(d(fy), { class: "ml-auto h-4 w-4" })
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), By = /* @__PURE__ */ T({
  __name: "DropdownMenuTrigger",
  props: {
    disabled: { type: Boolean },
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const n = wt(e);
    return (o, r) => (_(), E(d(Dg), z({ class: "outline-none" }, d(n)), {
      default: S(() => [
        P(o.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), ky = /* @__PURE__ */ T({
  __name: "Bar",
  props: {
    class: { default: "" }
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), oe("div", {
      class: le(d(fe)("h-5 animate-pulse bg-gray-300 w-full", t.class)),
      role: "progressbar",
      "aria-label": "Loading"
    }, null, 2));
  }
}), ac = /* @__PURE__ */ T({
  __name: "Spinner",
  props: {
    class: { default: "" }
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), oe("div", {
      class: le(
        d(fe)(
          "inline-block h-8 w-8 animate-spin rounded-full border-2 border-solid border-current border-e-transparent align-[-0.125em] text-primary motion-reduce:animate-[spin_1.5s_linear_infinite]",
          t.class
        )
      ),
      role: "status"
    }, o[0] || (o[0] = [
      Z("span", { class: "sr-only" }, "Loading...", -1)
    ]), 2));
  }
});
function My(e, t) {
  return _(), oe("svg", {
    xmlns: "http://www.w3.org/2000/svg",
    viewBox: "0 0 24 24",
    fill: "currentColor",
    "aria-hidden": "true",
    "data-slot": "icon"
  }, [
    Z("path", {
      "fill-rule": "evenodd",
      d: "M11.484 2.17a.75.75 0 0 1 1.032 0 11.209 11.209 0 0 0 7.877 3.08.75.75 0 0 1 .722.515 12.74 12.74 0 0 1 .635 3.985c0 5.942-4.064 10.933-9.563 12.348a.749.749 0 0 1-.374 0C6.314 20.683 2.25 15.692 2.25 9.75c0-1.39.223-2.73.635-3.985a.75.75 0 0 1 .722-.516l.143.001c2.996 0 5.718-1.17 7.734-3.08ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75ZM12 15a.75.75 0 0 0-.75.75v.008c0 .414.336.75.75.75h.008a.75.75 0 0 0 .75-.75v-.008a.75.75 0 0 0-.75-.75H12Z",
      "clip-rule": "evenodd"
    })
  ]);
}
const Dy = {
  key: 0,
  class: "contents"
}, Ry = {
  key: 1,
  class: "space-y-3"
}, Iy = { class: "flex justify-center" }, Ly = /* @__PURE__ */ T({
  __name: "Error",
  props: {
    class: { default: "" },
    loading: { type: Boolean },
    error: {}
  },
  emits: ["retry"],
  setup(e) {
    const t = e;
    return (n, o) => (_(), oe("div", {
      class: le(d(fe)("h-full flex flex-col items-center justify-center gap-3", t.class))
    }, [
      n.loading ? (_(), oe("div", Dy, [
        W(ac),
        o[1] || (o[1] = Z("p", null, "Loading Notifications...", -1))
      ])) : n.error ? (_(), oe("div", Ry, [
        Z("div", Iy, [
          W(d(My), { class: "h-10 text-unraid-red" })
        ]),
        Z("div", null, [
          o[2] || (o[2] = Z("h3", { class: "font-bold" }, xt("Error"), -1)),
          Z("p", null, xt(n.error.message), 1)
        ]),
        W(au, {
          type: "button",
          class: "w-full",
          onClick: o[0] || (o[0] = (r) => n.$emit("retry"))
        }, {
          default: S(() => o[3] || (o[3] = [
            At("Try Again")
          ])),
          _: 1
        })
      ])) : P(n.$slots, "default", { key: 2 })
    ], 2));
  }
});
typeof WorkerGlobalScope < "u" && globalThis instanceof WorkerGlobalScope;
const Fy = (e) => typeof e < "u";
function Vy(e) {
  return JSON.parse(JSON.stringify(e));
}
function Ny(e, t, n, o = {}) {
  var r, s, a;
  const {
    clone: i = !1,
    passive: l = !1,
    eventName: u,
    deep: c = !1,
    defaultValue: f,
    shouldEmit: p
  } = o, h = jt(), g = n || (h == null ? void 0 : h.emit) || ((r = h == null ? void 0 : h.$emit) == null ? void 0 : r.bind(h)) || ((a = (s = h == null ? void 0 : h.proxy) == null ? void 0 : s.$emit) == null ? void 0 : a.bind(h == null ? void 0 : h.proxy));
  let y = u;
  y = y || `update:${t.toString()}`;
  const C = (m) => i ? typeof i == "function" ? i(m) : Vy(m) : m, v = () => Fy(e[t]) ? C(e[t]) : f, w = (m) => {
    p ? p(m) && g(y, m) : g(y, m);
  };
  if (l) {
    const m = v(), x = B(m);
    let A = !1;
    return be(
      () => e[t],
      (R) => {
        A || (A = !0, x.value = C(R), Le(() => A = !1));
      }
    ), be(
      x,
      (R) => {
        !A && (R !== e[t] || c) && w(R);
      },
      { deep: c }
    ), x;
  } else
    return O({
      get() {
        return v();
      },
      set(m) {
        w(m);
      }
    });
}
const zy = /* @__PURE__ */ T({
  __name: "Input",
  props: {
    defaultValue: {},
    modelValue: {},
    class: {}
  },
  emits: ["update:modelValue"],
  setup(e, { emit: t }) {
    const n = e, r = Ny(n, "modelValue", t, {
      passive: !0,
      defaultValue: n.defaultValue
    });
    return (s, a) => fd((_(), oe("input", {
      "onUpdate:modelValue": a[0] || (a[0] = (i) => He(r) ? r.value = i : null),
      class: le(
        d(fe)(
          "flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50",
          n.class
        )
      )
    }, null, 2)), [
      [Ff, d(r)]
    ]);
  }
}), jy = /* @__PURE__ */ T({
  __name: "Label",
  props: {
    for: {},
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: o, ...r } = t;
      return r;
    });
    return (o, r) => (_(), E(d(ev), z(n.value, {
      class: d(fe)(
        "text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70",
        t.class
      )
    }), {
      default: S(() => [
        P(o.$slots, "default")
      ]),
      _: 3
    }, 16, ["class"]));
  }
});
function Hy(e, t, n) {
  let o = B(n == null ? void 0 : n.value), r = O(() => e.value !== void 0);
  return [O(() => r.value ? e.value : o.value), function(s) {
    return r.value || (o.value = s), t == null ? void 0 : t(s);
  }];
}
var Mi;
let Wy = Symbol("headlessui.useid"), Ky = 0;
const ic = (Mi = ds) != null ? Mi : function() {
  return Zt(Wy, () => `${++Ky}`)();
};
function Jo(e) {
  var t;
  if (e == null || e.value == null) return null;
  let n = (t = e.value.$el) != null ? t : e.value;
  return n instanceof Node ? n : null;
}
function lc(e, t, ...n) {
  if (e in t) {
    let r = t[e];
    return typeof r == "function" ? r(...n) : r;
  }
  let o = new Error(`Tried to handle "${e}" but there is no handler defined. Only defined handlers are: ${Object.keys(t).map((r) => `"${r}"`).join(", ")}.`);
  throw Error.captureStackTrace && Error.captureStackTrace(o, lc), o;
}
function Di(e, t) {
  if (e) return e;
  let n = t ?? "button";
  if (typeof n == "string" && n.toLowerCase() === "button") return "button";
}
function Uy(e, t) {
  let n = B(Di(e.value.type, e.value.as));
  return Se(() => {
    n.value = Di(e.value.type, e.value.as);
  }), Me(() => {
    var o;
    n.value || Jo(t) && Jo(t) instanceof HTMLButtonElement && !((o = Jo(t)) != null && o.hasAttribute("type")) && (n.value = "button");
  }), n;
}
var Gy = ((e) => (e[e.None = 0] = "None", e[e.RenderStrategy = 1] = "RenderStrategy", e[e.Static = 2] = "Static", e))(Gy || {}), Yy = ((e) => (e[e.Unmount = 0] = "Unmount", e[e.Hidden = 1] = "Hidden", e))(Yy || {});
function Nr({ visible: e = !0, features: t = 0, ourProps: n, theirProps: o, ...r }) {
  var s;
  let a = cc(o, n), i = Object.assign(r, { props: a });
  if (e || t & 2 && a.static) return rs(i);
  if (t & 1) {
    let l = (s = a.unmount) == null || s ? 0 : 1;
    return lc(l, { 0() {
      return null;
    }, 1() {
      return rs({ ...r, props: { ...a, hidden: !0, style: { display: "none" } } });
    } });
  }
  return rs(i);
}
function rs({ props: e, attrs: t, slots: n, slot: o, name: r }) {
  var s, a;
  let { as: i, ...l } = dc(e, ["unmount", "static"]), u = (s = n.default) == null ? void 0 : s.call(n, o), c = {};
  if (o) {
    let f = !1, p = [];
    for (let [h, g] of Object.entries(o)) typeof g == "boolean" && (f = !0), g === !0 && p.push(h);
    f && (c["data-headlessui-state"] = p.join(" "));
  }
  if (i === "template") {
    if (u = uc(u ?? []), Object.keys(l).length > 0 || Object.keys(t).length > 0) {
      let [f, ...p] = u ?? [];
      if (!Xy(f) || p.length > 0) throw new Error(['Passing props on "template"!', "", `The current component <${r} /> is rendering a "template".`, "However we need to passthrough the following props:", Object.keys(l).concat(Object.keys(t)).map((y) => y.trim()).filter((y, C, v) => v.indexOf(y) === C).sort((y, C) => y.localeCompare(C)).map((y) => `  - ${y}`).join(`
`), "", "You can apply a few solutions:", ['Add an `as="..."` prop, to ensure that we render an actual element instead of a "template".', "Render a single element as the child so that we can forward the props onto that element."].map((y) => `  - ${y}`).join(`
`)].join(`
`));
      let h = cc((a = f.props) != null ? a : {}, l, c), g = mn(f, h, !0);
      for (let y in h) y.startsWith("on") && (g.props || (g.props = {}), g.props[y] = h[y]);
      return g;
    }
    return Array.isArray(u) && u.length === 1 ? u[0] : u;
  }
  return St(i, Object.assign({}, l, c), { default: () => u });
}
function uc(e) {
  return e.flatMap((t) => t.type === ke ? uc(t.children) : [t]);
}
function cc(...e) {
  if (e.length === 0) return {};
  if (e.length === 1) return e[0];
  let t = {}, n = {};
  for (let o of e) for (let r in o) r.startsWith("on") && typeof o[r] == "function" ? (n[r] != null || (n[r] = []), n[r].push(o[r])) : t[r] = o[r];
  if (t.disabled || t["aria-disabled"]) return Object.assign(t, Object.fromEntries(Object.keys(n).map((o) => [o, void 0])));
  for (let o in n) Object.assign(t, { [o](r, ...s) {
    let a = n[o];
    for (let i of a) {
      if (r instanceof Event && r.defaultPrevented) return;
      i(r, ...s);
    }
  } });
  return t;
}
function qy(e) {
  let t = Object.assign({}, e);
  for (let n in t) t[n] === void 0 && delete t[n];
  return t;
}
function dc(e, t = []) {
  let n = Object.assign({}, e);
  for (let o of t) o in n && delete n[o];
  return n;
}
function Xy(e) {
  return e == null ? !1 : typeof e.type == "string" || typeof e.type == "object" || typeof e.type == "function";
}
var fc = ((e) => (e[e.None = 1] = "None", e[e.Focusable = 2] = "Focusable", e[e.Hidden = 4] = "Hidden", e))(fc || {});
let Jy = /* @__PURE__ */ T({ name: "Hidden", props: { as: { type: [Object, String], default: "div" }, features: { type: Number, default: 1 } }, setup(e, { slots: t, attrs: n }) {
  return () => {
    var o;
    let { features: r, ...s } = e, a = { "aria-hidden": (r & 2) === 2 ? !0 : (o = s["aria-hidden"]) != null ? o : void 0, hidden: (r & 4) === 4 ? !0 : void 0, style: { position: "fixed", top: 1, left: 1, width: 1, height: 0, padding: 0, margin: -1, overflow: "hidden", clip: "rect(0, 0, 0, 0)", whiteSpace: "nowrap", borderWidth: "0", ...(r & 4) === 4 && (r & 2) !== 2 && { display: "none" } } };
    return Nr({ ourProps: a, theirProps: s, slot: {}, attrs: n, slots: t, name: "Hidden" });
  };
} });
var Ms = ((e) => (e.Space = " ", e.Enter = "Enter", e.Escape = "Escape", e.Backspace = "Backspace", e.Delete = "Delete", e.ArrowLeft = "ArrowLeft", e.ArrowUp = "ArrowUp", e.ArrowRight = "ArrowRight", e.ArrowDown = "ArrowDown", e.Home = "Home", e.End = "End", e.PageUp = "PageUp", e.PageDown = "PageDown", e.Tab = "Tab", e))(Ms || {});
function Zy(e) {
  var t, n;
  let o = (t = e == null ? void 0 : e.form) != null ? t : e.closest("form");
  if (o) {
    for (let r of o.elements) if (r !== e && (r.tagName === "INPUT" && r.type === "submit" || r.tagName === "BUTTON" && r.type === "submit" || r.nodeName === "INPUT" && r.type === "image")) {
      r.click();
      return;
    }
    (n = o.requestSubmit) == null || n.call(o);
  }
}
let Qy = Symbol("DescriptionContext");
function eb({ slot: e = B({}), name: t = "Description", props: n = {} } = {}) {
  let o = B([]);
  function r(s) {
    return o.value.push(s), () => {
      let a = o.value.indexOf(s);
      a !== -1 && o.value.splice(a, 1);
    };
  }
  return Jn(Qy, { register: r, slot: e, name: t, props: n }), O(() => o.value.length > 0 ? o.value.join(" ") : void 0);
}
let pc = Symbol("LabelContext");
function hc() {
  let e = Zt(pc, null);
  if (e === null) {
    let t = new Error("You used a <Label /> component, but it is not inside a parent.");
    throw Error.captureStackTrace && Error.captureStackTrace(t, hc), t;
  }
  return e;
}
function tb({ slot: e = {}, name: t = "Label", props: n = {} } = {}) {
  let o = B([]);
  function r(s) {
    return o.value.push(s), () => {
      let a = o.value.indexOf(s);
      a !== -1 && o.value.splice(a, 1);
    };
  }
  return Jn(pc, { register: r, slot: e, name: t, props: n }), O(() => o.value.length > 0 ? o.value.join(" ") : void 0);
}
let nb = /* @__PURE__ */ T({ name: "Label", props: { as: { type: [Object, String], default: "label" }, passive: { type: [Boolean], default: !1 }, id: { type: String, default: null } }, setup(e, { slots: t, attrs: n }) {
  var o;
  let r = (o = e.id) != null ? o : `headlessui-label-${ic()}`, s = hc();
  return Se(() => bt(s.register(r))), () => {
    let { name: a = "Label", slot: i = {}, props: l = {} } = s, { passive: u, ...c } = e, f = { ...Object.entries(l).reduce((p, [h, g]) => Object.assign(p, { [h]: d(g) }), {}), id: r };
    return u && (delete f.onClick, delete f.htmlFor, delete c.onClick), Nr({ ourProps: f, theirProps: c, slot: i, attrs: n, slots: t, name: a });
  };
} }), mc = Symbol("GroupContext"), ob = /* @__PURE__ */ T({ name: "SwitchGroup", props: { as: { type: [Object, String], default: "template" } }, setup(e, { slots: t, attrs: n }) {
  let o = B(null), r = tb({ name: "SwitchLabel", props: { htmlFor: O(() => {
    var a;
    return (a = o.value) == null ? void 0 : a.id;
  }), onClick(a) {
    o.value && (a.currentTarget.tagName === "LABEL" && a.preventDefault(), o.value.click(), o.value.focus({ preventScroll: !0 }));
  } } }), s = eb({ name: "SwitchDescription" });
  return Jn(mc, { switchRef: o, labelledby: r, describedby: s }), () => Nr({ theirProps: e, ourProps: {}, slot: {}, slots: t, attrs: n, name: "SwitchGroup" });
} }), rb = /* @__PURE__ */ T({ name: "Switch", emits: { "update:modelValue": (e) => !0 }, props: { as: { type: [Object, String], default: "button" }, modelValue: { type: Boolean, default: void 0 }, defaultChecked: { type: Boolean, optional: !0 }, form: { type: String, optional: !0 }, name: { type: String, optional: !0 }, value: { type: String, optional: !0 }, id: { type: String, default: null }, disabled: { type: Boolean, default: !1 }, tabIndex: { type: Number, default: 0 } }, inheritAttrs: !1, setup(e, { emit: t, attrs: n, slots: o, expose: r }) {
  var s;
  let a = (s = e.id) != null ? s : `headlessui-switch-${ic()}`, i = Zt(mc, null), [l, u] = Hy(O(() => e.modelValue), (w) => t("update:modelValue", w), O(() => e.defaultChecked));
  function c() {
    u(!l.value);
  }
  let f = B(null), p = i === null ? f : i.switchRef, h = Uy(O(() => ({ as: e.as, type: n.type })), p);
  r({ el: p, $el: p });
  function g(w) {
    w.preventDefault(), c();
  }
  function y(w) {
    w.key === Ms.Space ? (w.preventDefault(), c()) : w.key === Ms.Enter && Zy(w.currentTarget);
  }
  function C(w) {
    w.preventDefault();
  }
  let v = O(() => {
    var w, m;
    return (m = (w = Jo(p)) == null ? void 0 : w.closest) == null ? void 0 : m.call(w, "form");
  });
  return Se(() => {
    be([v], () => {
      if (!v.value || e.defaultChecked === void 0) return;
      function w() {
        u(e.defaultChecked);
      }
      return v.value.addEventListener("reset", w), () => {
        var m;
        (m = v.value) == null || m.removeEventListener("reset", w);
      };
    }, { immediate: !0 });
  }), () => {
    let { name: w, value: m, form: x, tabIndex: A, ...R } = e, I = { checked: l.value }, H = { id: a, ref: p, role: "switch", type: h.value, tabIndex: A === -1 ? 0 : A, "aria-checked": l.value, "aria-labelledby": i == null ? void 0 : i.labelledby.value, "aria-describedby": i == null ? void 0 : i.describedby.value, onClick: g, onKeyup: y, onKeypress: C };
    return St(ke, [w != null && l.value != null ? St(Jy, qy({ features: fc.Hidden, as: "input", type: "checkbox", hidden: !0, readOnly: !0, checked: l.value, form: x, disabled: R.disabled, name: w, value: m })) : null, Nr({ ourProps: H, theirProps: { ...n, ...dc(R, ["modelValue", "defaultChecked"]) }, slot: I, attrs: n, slots: o, name: "Switch" })]);
  };
} }), sb = nb;
const ab = { class: "flex flex-shrink-0 items-center gap-16px" }, ib = /* @__PURE__ */ T({
  __name: "Lightswitch",
  props: {
    label: {}
  },
  setup(e) {
    const t = B(!1);
    return (n, o) => (_(), E(d(ob), { as: "div" }, {
      default: S(() => [
        Z("div", ab, [
          W(d(rb), {
            modelValue: t.value,
            "onUpdate:modelValue": o[0] || (o[0] = (r) => t.value = r),
            class: le([
              t.value ? "bg-green-500" : "bg-gray-200",
              "relative inline-flex h-24px w-[44px] flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
            ])
          }, {
            default: S(() => [
              Z("span", {
                class: le([
                  t.value ? "translate-x-20px" : "translate-x-0",
                  "pointer-events-none relative inline-block h-20px w-20px transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                ])
              }, [
                Z("span", {
                  class: le([
                    t.value ? "opacity-0 duration-100 ease-out" : "opacity-100 duration-200 ease-in",
                    "absolute inset-0 flex h-full w-full items-center justify-center transition-opacity"
                  ]),
                  "aria-hidden": "true"
                }, o[1] || (o[1] = [
                  Z("svg", {
                    class: "h-12px w-12px text-gray-400",
                    fill: "none",
                    viewBox: "0 0 12 12"
                  }, [
                    Z("path", {
                      d: "M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2",
                      stroke: "currentColor",
                      "stroke-width": "2",
                      "stroke-linecap": "round",
                      "stroke-linejoin": "round"
                    })
                  ], -1)
                ]), 2),
                Z("span", {
                  class: le([
                    t.value ? "opacity-100 duration-200 ease-in" : "opacity-0 duration-100 ease-out",
                    "absolute inset-0 flex h-full w-full items-center justify-center transition-opacity"
                  ]),
                  "aria-hidden": "true"
                }, o[2] || (o[2] = [
                  Z("svg", {
                    class: "h-12px w-12px text-green-500",
                    fill: "currentColor",
                    viewBox: "0 0 12 12"
                  }, [
                    Z("path", { d: "M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z" })
                  ], -1)
                ]), 2)
              ], 2)
            ]),
            _: 1
          }, 8, ["modelValue", "class"]),
          W(d(sb), { class: "text-14px" }, {
            default: S(() => [
              At(xt(n.label), 1)
            ]),
            _: 1
          })
        ])
      ]),
      _: 1
    }));
  }
}), lb = /* @__PURE__ */ T({
  __name: "Select",
  props: {
    open: { type: Boolean },
    defaultOpen: { type: Boolean },
    defaultValue: {},
    modelValue: {},
    by: { type: [String, Function] },
    dir: {},
    multiple: { type: Boolean },
    autocomplete: {},
    disabled: { type: Boolean },
    name: {},
    required: { type: Boolean }
  },
  emits: ["update:modelValue", "update:open"],
  setup(e, { emit: t }) {
    const r = Ne(e, t);
    return (s, a) => (_(), E(d(wv), me(we(d(r))), {
      default: S(() => [
        P(s.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), ub = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "SelectContent",
  props: {
    forceMount: { type: Boolean, default: !1 },
    position: { default: "popper" },
    bodyLock: { type: Boolean },
    side: {},
    sideOffset: {},
    align: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    disableUpdateOnLayoutShift: { type: Boolean },
    prioritizePosition: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  emits: ["closeAutoFocus", "escapeKeyDown", "pointerDownOutside"],
  setup(e, { emit: t }) {
    const { teleportTarget: n } = Vr(), o = e, r = t, s = O(() => {
      const { class: i, ...l } = o;
      return l;
    }), a = Ne(s, r);
    return (i, l) => (_(), E(d(xv), {
      "force-mount": i.forceMount,
      to: d(n)
    }, {
      default: S(() => [
        W(d(Ov), z({ ...d(a), ...i.$attrs }, {
          class: d(fe)(
            "relative z-50 max-h-96 min-w-32 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2",
            i.position === "popper" && "data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1",
            o.class
          )
        }), {
          default: S(() => [
            W(d(vc)),
            W(d(Vv), {
              class: le(
                d(fe)(
                  "p-1",
                  i.position === "popper" && "h-[--reka-select-trigger-height] w-full min-w-[--reka-select-trigger-width]"
                )
              )
            }, {
              default: S(() => [
                P(i.$slots, "default")
              ]),
              _: 3
            }, 8, ["class"]),
            W(d(gc))
          ]),
          _: 3
        }, 16, ["class"])
      ]),
      _: 3
    }, 8, ["force-mount", "to"]));
  }
}), cb = /* @__PURE__ */ T({
  __name: "SelectGroup",
  props: {
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: o, ...r } = t;
      return r;
    });
    return (o, r) => (_(), E(d(Lv), z({
      class: d(fe)("p-1 w-full", t.class)
    }, n.value), {
      default: S(() => [
        P(o.$slots, "default")
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), db = { class: "absolute left-2 flex h-3.5 w-3.5 items-center justify-center" }, fb = /* @__PURE__ */ T({
  __name: "SelectItem",
  props: {
    value: {},
    disabled: { type: Boolean },
    textValue: {},
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: r, ...s } = t;
      return s;
    }), o = wt(n);
    return (r, s) => (_(), E(d(Mv), z(d(o), {
      class: d(fe)(
        "relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50",
        t.class
      )
    }), {
      default: S(() => [
        Z("span", db, [
          W(d(Dv), null, {
            default: S(() => [
              W(d(rc), { class: "h-4 w-4" })
            ]),
            _: 1
          })
        ]),
        W(d(Zu), null, {
          default: S(() => [
            P(r.$slots, "default")
          ]),
          _: 3
        })
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), pb = /* @__PURE__ */ T({
  __name: "SelectItemText",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(Zu), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), hb = /* @__PURE__ */ T({
  __name: "SelectLabel",
  props: {
    for: {},
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(Fv), {
      class: le(d(fe)("py-1.5 pl-8 pr-2 text-sm font-semibold", t.class))
    }, {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 8, ["class"]));
  }
}), gc = /* @__PURE__ */ T({
  __name: "SelectScrollDownButton",
  props: {
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: r, ...s } = t;
      return s;
    }), o = wt(n);
    return (r, s) => (_(), E(d(zv), z(d(o), {
      class: d(fe)("flex cursor-default items-center justify-center py-1", t.class)
    }), {
      default: S(() => [
        P(r.$slots, "default", {}, () => [
          W(d(sc), { class: "h-4 w-4" })
        ])
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), vc = /* @__PURE__ */ T({
  __name: "SelectScrollUpButton",
  props: {
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: r, ...s } = t;
      return s;
    }), o = wt(n);
    return (r, s) => (_(), E(d(Nv), z(d(o), {
      class: d(fe)("flex cursor-default items-center justify-center py-1", t.class)
    }), {
      default: S(() => [
        P(r.$slots, "default", {}, () => [
          W(d(py), { class: "h-4 w-4" })
        ])
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), mb = /* @__PURE__ */ T({
  __name: "SelectSeparator",
  props: {
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: o, ...r } = t;
      return r;
    });
    return (o, r) => (_(), E(d(Bv), z(n.value, {
      class: d(fe)("-mx-1 my-1 h-px bg-muted", t.class)
    }), null, 16, ["class"]));
  }
}), gb = /* @__PURE__ */ T({
  __name: "SelectTrigger",
  props: {
    disabled: { type: Boolean },
    reference: {},
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: r, ...s } = t;
      return s;
    }), o = wt(n);
    return (r, s) => (_(), E(d(_v), z(d(o), {
      class: d(fe)(
        "flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background data-[placeholder]:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 [&>span]:truncate text-start",
        t.class
      )
    }), {
      default: S(() => [
        P(r.$slots, "default"),
        W(d(Hv), { "as-child": "" }, {
          default: S(() => [
            W(d(sc), { class: "w-4 h-4 opacity-50 shrink-0" })
          ]),
          _: 1
        })
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), vb = /* @__PURE__ */ T({
  __name: "SelectValue",
  props: {
    placeholder: {},
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(jv), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), yb = /* @__PURE__ */ T({
  __name: "Switch",
  props: {
    defaultValue: { type: Boolean },
    modelValue: { type: [Boolean, null] },
    disabled: { type: Boolean },
    id: {},
    value: {},
    asChild: { type: Boolean },
    as: {},
    name: {},
    required: { type: Boolean },
    class: {}
  },
  emits: ["update:modelValue"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = O(() => {
      const { class: a, ...i } = n;
      return i;
    }), s = Ne(r, o);
    return (a, i) => (_(), E(d(Uv), z(d(s), {
      class: d(fe)(
        "peer inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input",
        n.class
      )
    }), {
      default: S(() => [
        W(d(Gv), {
          class: le(
            d(fe)(
              "pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform data-[state=checked]:translate-x-5 data-[state=unchecked]:translate-x-0"
            )
          )
        }, null, 8, ["class"])
      ]),
      _: 1
    }, 16, ["class"]));
  }
}), yc = /* @__PURE__ */ T({
  __name: "ScrollBar",
  props: {
    orientation: { default: "vertical" },
    forceMount: { type: Boolean },
    asChild: { type: Boolean },
    as: {},
    class: { default: void 0 }
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: o, ...r } = t;
      return r;
    });
    return (o, r) => (_(), E(d(fv), z(n.value, {
      class: d(fe)(
        "flex touch-none select-none transition-colors",
        o.orientation === "vertical" && "h-full w-2.5 border-l border-l-transparent p-px",
        o.orientation === "horizontal" && "h-2.5 flex-col border-t border-t-transparent p-px",
        t.class
      )
    }), {
      default: S(() => [
        W(d(pv), { class: "relative flex-1 rounded-full bg-border" })
      ]),
      _: 1
    }, 16, ["class"]));
  }
}), bb = /* @__PURE__ */ T({
  __name: "ScrollArea",
  props: {
    type: {},
    dir: {},
    scrollHideDelay: {},
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: o, ...r } = t;
      return r;
    });
    return (o, r) => (_(), E(d(nv), z(n.value, {
      class: d(fe)("relative overflow-hidden", t.class)
    }), {
      default: S(() => [
        W(d(ov), { class: "h-full w-full rounded-[inherit]" }, {
          default: S(() => [
            P(o.$slots, "default")
          ]),
          _: 3
        }),
        W(yc),
        W(d(mv))
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), wb = "width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0", _b = /* @__PURE__ */ T({
  __name: "Sheet",
  props: {
    open: { type: Boolean },
    defaultOpen: { type: Boolean },
    modal: { type: Boolean }
  },
  emits: ["update:open"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = () => {
      var u;
      return ((u = document.querySelector('meta[name="viewport"]')) == null ? void 0 : u.getAttribute("content")) ?? "width=1300";
    }, s = (u) => {
      if (window.innerWidth < 500) {
        const c = document.querySelector('meta[name="viewport"]');
        if (c)
          c.setAttribute("content", u);
        else {
          const f = document.createElement("meta");
          f.name = "viewport", f.content = u, document.head.appendChild(f);
        }
      }
    }, a = Ne(n, o), i = B(r()), l = (u) => {
      s(u ? wb : i.value);
    };
    return bt(() => {
      s(i.value);
    }), (u, c) => (_(), E(d(cm), z(d(a), { "onUpdate:open": l }), {
      default: S(() => [
        P(u.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), xb = /* @__PURE__ */ T({
  __name: "SheetTrigger",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(dm), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Cb = /* @__PURE__ */ T({
  __name: "SheetClose",
  props: {
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(Au), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Sb = Bo(
  "fixed z-50 bg-background gap-4 shadow-lg transition ease-in-out data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:duration-300 data-[state=open]:duration-500 border-border",
  {
    variants: {
      side: {
        top: "inset-x-0 top-0 border-b data-[state=closed]:slide-out-to-top data-[state=open]:slide-in-from-top",
        bottom: "inset-x-0 bottom-0 border-t data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom",
        left: "inset-y-0 left-0 h-full w-3/4 border-r data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left sm:max-w-sm",
        right: "inset-y-0 right-0 h-full w-3/4 border-l data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right sm:max-w-sm"
      },
      padding: {
        none: "",
        md: "p-6"
      }
    },
    defaultVariants: {
      side: "right",
      padding: "md"
    }
  }
), $b = /* @__PURE__ */ T({
  __name: "SheetContent",
  props: {
    side: { default: "right" },
    padding: { default: "md" },
    class: {},
    disabled: { type: Boolean },
    forceMount: { type: Boolean },
    to: {}
  },
  emits: ["escapeKeyDown", "pointerDownOutside", "focusOutside", "interactOutside", "openAutoFocus", "closeAutoFocus"],
  setup(e, { emit: t }) {
    const { teleportTarget: n } = Vr(), o = e, r = t, s = O(() => fe(Sb({ side: o.side, padding: o.padding }), o.class)), a = O(() => {
      const { class: l, side: u, padding: c, ...f } = o;
      return f;
    }), i = Ne(a, r);
    return (l, u) => (_(), E(d(Bg), {
      disabled: l.disabled,
      "force-mount": l.forceMount,
      to: d(n)
    }, {
      default: S(() => [
        W(d(jm), { class: "fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" }),
        W(d(Im), z({ class: s.value }, d(i)), {
          default: S(() => [
            P(l.$slots, "default"),
            W(d(Au), { class: "absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none data-[state=open]:bg-secondary" }, {
              default: S(() => [
                W(d(my), { class: "w-4 h-4 text-muted-foreground" })
              ]),
              _: 1
            })
          ]),
          _: 3
        }, 16, ["class"])
      ]),
      _: 3
    }, 8, ["disabled", "force-mount", "to"]));
  }
}), Tb = /* @__PURE__ */ T({
  __name: "SheetHeader",
  props: {
    class: {}
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), oe("div", {
      class: le(d(fe)("flex flex-col gap-y-2 text-center sm:text-left", t.class))
    }, [
      P(n.$slots, "default")
    ], 2));
  }
}), Pb = /* @__PURE__ */ T({
  __name: "SheetTitle",
  props: {
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: o, ...r } = t;
      return r;
    });
    return (o, r) => (_(), E(d(Hm), z({
      class: d(fe)("text-lg font-medium text-foreground", t.class)
    }, n.value), {
      default: S(() => [
        P(o.$slots, "default")
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), Eb = /* @__PURE__ */ T({
  __name: "SheetDescription",
  props: {
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: o, ...r } = t;
      return r;
    });
    return (o, r) => (_(), E(d(Wm), z({
      class: d(fe)("text-sm text-muted-foreground", t.class)
    }, n.value), {
      default: S(() => [
        P(o.$slots, "default")
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), Ab = /* @__PURE__ */ T({
  __name: "SheetFooter",
  props: {
    class: {}
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), oe("div", {
      class: le(d(fe)("flex flex-col-reverse sm:flex-row sm:justify-end sm:gap-x-2", t.class))
    }, [
      P(n.$slots, "default")
    ], 2));
  }
}), Ob = /* @__PURE__ */ T({
  __name: "Tabs",
  props: {
    defaultValue: {},
    orientation: {},
    dir: {},
    activationMode: {},
    modelValue: {},
    unmountOnHide: { type: Boolean },
    asChild: { type: Boolean },
    as: {}
  },
  emits: ["update:modelValue"],
  setup(e, { emit: t }) {
    const r = Ne(e, t);
    return (s, a) => (_(), E(d(qv), me(we(d(r))), {
      default: S(() => [
        P(s.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Bb = { class: "truncate" }, kb = /* @__PURE__ */ T({
  __name: "TabsTrigger",
  props: {
    value: {},
    disabled: { type: Boolean },
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: r, ...s } = t;
      return s;
    }), o = wt(n);
    return (r, s) => (_(), E(d(Zv), z(d(o), {
      class: d(fe)(
        "inline-flex items-center justify-center whitespace-nowrap rounded px-4.5 py-2.5 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm",
        t.class
      )
    }), {
      default: S(() => [
        Z("span", Bb, [
          P(r.$slots, "default")
        ])
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), Mb = /* @__PURE__ */ T({
  __name: "TabsList",
  props: {
    loop: { type: Boolean },
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: o, ...r } = t;
      return r;
    });
    return (o, r) => (_(), E(d(Xv), z(n.value, {
      class: d(fe)(
        "inline-flex items-center justify-center rounded-md bg-input p-1.5 text-foreground",
        t.class
      )
    }), {
      default: S(() => [
        P(o.$slots, "default")
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), Db = /* @__PURE__ */ T({
  __name: "TabsContent",
  props: {
    value: {},
    forceMount: { type: Boolean },
    asChild: { type: Boolean },
    as: {},
    class: {}
  },
  setup(e) {
    const t = e, n = O(() => {
      const { class: o, ...r } = t;
      return r;
    });
    return (o, r) => (_(), E(d(Jv), z(n.value, {
      class: [
        d(fe)(
          "flex mt-2 ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
          t.class
        ),
        "data-[state=active]:flex data-[state=inactive]:hidden"
      ]
    }), {
      default: S(() => [
        P(o.$slots, "default")
      ]),
      _: 3
    }, 16, ["class"]));
  }
}), Rb = /* @__PURE__ */ T({
  __name: "Tooltip",
  props: {
    defaultOpen: { type: Boolean },
    open: { type: Boolean },
    delayDuration: {},
    disableHoverableContent: { type: Boolean },
    disableClosingTrigger: { type: Boolean },
    disabled: { type: Boolean },
    ignoreNonKeyboardFocus: { type: Boolean }
  },
  emits: ["update:open"],
  setup(e, { emit: t }) {
    const r = Ne(e, t);
    return (s, a) => (_(), E(d(ny), me(we(d(r))), {
      default: S(() => [
        P(s.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Ib = /* @__PURE__ */ T({
  inheritAttrs: !1,
  __name: "TooltipContent",
  props: {
    forceMount: { type: Boolean },
    ariaLabel: {},
    asChild: { type: Boolean },
    as: {},
    side: {},
    sideOffset: { default: 4 },
    align: {},
    alignOffset: {},
    avoidCollisions: { type: Boolean },
    collisionBoundary: {},
    collisionPadding: {},
    arrowPadding: {},
    sticky: {},
    hideWhenDetached: { type: Boolean },
    positionStrategy: {},
    updatePositionStrategy: {},
    class: { default: void 0 }
  },
  emits: ["escapeKeyDown", "pointerDownOutside"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = O(() => {
      const { class: i, ...l } = n;
      return l;
    }), s = Ne(r, o), { teleportTarget: a } = Vr();
    return (i, l) => (_(), E(d(ay), {
      to: d(a),
      defer: ""
    }, {
      default: S(() => [
        W(d(sy), z({ ...d(s), ...i.$attrs }, {
          class: d(fe)(
            "z-50 overflow-hidden rounded-md border bg-popover px-3 py-1.5 text-sm text-popover-foreground shadow-md animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2",
            n.class
          )
        }), {
          default: S(() => [
            P(i.$slots, "default")
          ]),
          _: 3
        }, 16, ["class"])
      ]),
      _: 3
    }, 8, ["to"]));
  }
}), Lb = /* @__PURE__ */ T({
  __name: "TooltipTrigger",
  props: {
    reference: {},
    asChild: { type: Boolean },
    as: {}
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(oy), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
}), Fb = /* @__PURE__ */ T({
  __name: "TooltipProvider",
  props: {
    delayDuration: {},
    skipDelayDuration: {},
    disableHoverableContent: { type: Boolean },
    disableClosingTrigger: { type: Boolean },
    disabled: { type: Boolean },
    ignoreNonKeyboardFocus: { type: Boolean }
  },
  setup(e) {
    const t = e;
    return (n, o) => (_(), E(d(ey), me(we(t)), {
      default: S(() => [
        P(n.$slots, "default")
      ]),
      _: 3
    }, 16));
  }
});
var Vb = Object.defineProperty, Nb = (e, t, n) => t in e ? Vb(e, t, { enumerable: !0, configurable: !0, writable: !0, value: n }) : e[t] = n, rt = (e, t, n) => Nb(e, typeof t != "symbol" ? t + "" : t, n);
function zb(e) {
  if (typeof document > "u") return;
  let t = document.head || document.getElementsByTagName("head")[0], n = document.createElement("style");
  n.type = "text/css", t.appendChild(n), n.styleSheet ? n.styleSheet.cssText = e : n.appendChild(document.createTextNode(e));
}
zb(":where([data-sonner-toaster][dir=ltr]),:where(html[dir=ltr]){--toast-icon-margin-start:-3px;--toast-icon-margin-end:4px;--toast-svg-margin-start:-1px;--toast-svg-margin-end:0px;--toast-button-margin-start:auto;--toast-button-margin-end:0;--toast-close-button-start:0;--toast-close-button-end:unset;--toast-close-button-transform:translate(-35%, -35%)}:where([data-sonner-toaster][dir=rtl]),:where(html[dir=rtl]){--toast-icon-margin-start:4px;--toast-icon-margin-end:-3px;--toast-svg-margin-start:0px;--toast-svg-margin-end:-1px;--toast-button-margin-start:0;--toast-button-margin-end:auto;--toast-close-button-start:unset;--toast-close-button-end:0;--toast-close-button-transform:translate(35%, -35%)}:where([data-sonner-toaster]){position:fixed;width:var(--width);font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,Noto Sans,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji;--gray1:hsl(0, 0%, 99%);--gray2:hsl(0, 0%, 97.3%);--gray3:hsl(0, 0%, 95.1%);--gray4:hsl(0, 0%, 93%);--gray5:hsl(0, 0%, 90.9%);--gray6:hsl(0, 0%, 88.7%);--gray7:hsl(0, 0%, 85.8%);--gray8:hsl(0, 0%, 78%);--gray9:hsl(0, 0%, 56.1%);--gray10:hsl(0, 0%, 52.3%);--gray11:hsl(0, 0%, 43.5%);--gray12:hsl(0, 0%, 9%);--border-radius:8px;box-sizing:border-box;padding:0;margin:0;list-style:none;outline:0;z-index:999999999;transition:transform .4s ease}:where([data-sonner-toaster][data-lifted=true]){transform:translateY(-10px)}@media (hover:none) and (pointer:coarse){:where([data-sonner-toaster][data-lifted=true]){transform:none}}:where([data-sonner-toaster][data-x-position=right]){right:max(var(--offset),env(safe-area-inset-right))}:where([data-sonner-toaster][data-x-position=left]){left:max(var(--offset),env(safe-area-inset-left))}:where([data-sonner-toaster][data-x-position=center]){left:50%;transform:translateX(-50%)}:where([data-sonner-toaster][data-y-position=top]){top:max(var(--offset),env(safe-area-inset-top))}:where([data-sonner-toaster][data-y-position=bottom]){bottom:max(var(--offset),env(safe-area-inset-bottom))}:where([data-sonner-toast]){--y:translateY(100%);--lift-amount:calc(var(--lift) * var(--gap));z-index:var(--z-index);position:absolute;opacity:0;transform:var(--y);filter:blur(0);touch-action:none;transition:transform .4s,opacity .4s,height .4s,box-shadow .2s;box-sizing:border-box;outline:0;overflow-wrap:anywhere}:where([data-sonner-toast][data-styled=true]){padding:16px;background:var(--normal-bg);border:1px solid var(--normal-border);color:var(--normal-text);border-radius:var(--border-radius);box-shadow:0 4px 12px rgba(0,0,0,.1);width:var(--width);font-size:13px;display:flex;align-items:center;gap:6px}:where([data-sonner-toast]:focus-visible){box-shadow:0 4px 12px rgba(0,0,0,.1),0 0 0 2px rgba(0,0,0,.2)}:where([data-sonner-toast][data-y-position=top]){top:0;--y:translateY(-100%);--lift:1;--lift-amount:calc(1 * var(--gap))}:where([data-sonner-toast][data-y-position=bottom]){bottom:0;--y:translateY(100%);--lift:-1;--lift-amount:calc(var(--lift) * var(--gap))}:where([data-sonner-toast]) :where([data-description]){font-weight:400;line-height:1.4;color:inherit}:where([data-sonner-toast]) :where([data-title]){font-weight:500;line-height:1.5;color:inherit}:where([data-sonner-toast]) :where([data-icon]){display:flex;height:16px;width:16px;position:relative;justify-content:flex-start;align-items:center;flex-shrink:0;margin-left:var(--toast-icon-margin-start);margin-right:var(--toast-icon-margin-end)}:where([data-sonner-toast][data-promise=true]) :where([data-icon])>svg{opacity:0;transform:scale(.8);transform-origin:center;animation:sonner-fade-in .3s ease forwards}:where([data-sonner-toast]) :where([data-icon])>*{flex-shrink:0}:where([data-sonner-toast]) :where([data-icon]) svg{margin-left:var(--toast-svg-margin-start);margin-right:var(--toast-svg-margin-end)}:where([data-sonner-toast]) :where([data-content]){display:flex;flex-direction:column;gap:2px}[data-sonner-toast][data-styled=true] [data-button]{border-radius:4px;padding-left:8px;padding-right:8px;height:24px;font-size:12px;color:var(--normal-bg);background:var(--normal-text);margin-left:var(--toast-button-margin-start);margin-right:var(--toast-button-margin-end);border:none;cursor:pointer;outline:0;display:flex;align-items:center;flex-shrink:0;transition:opacity .4s,box-shadow .2s}:where([data-sonner-toast]) :where([data-button]):focus-visible{box-shadow:0 0 0 2px rgba(0,0,0,.4)}:where([data-sonner-toast]) :where([data-button]):first-of-type{margin-left:var(--toast-button-margin-start);margin-right:var(--toast-button-margin-end)}:where([data-sonner-toast]) :where([data-cancel]){color:var(--normal-text);background:rgba(0,0,0,.08)}:where([data-sonner-toast][data-theme=dark]) :where([data-cancel]){background:rgba(255,255,255,.3)}[data-sonner-toast] [data-close-button]{position:absolute;left:var(--toast-close-button-start);right:var(--toast-close-button-end);top:0;height:20px;width:20px;display:flex;justify-content:center;align-items:center;padding:0;color:var(--gray12);border:1px solid var(--gray4);transform:var(--toast-close-button-transform);border-radius:50%;cursor:pointer;z-index:1;transition:opacity .1s,background .2s,border-color .2s}[data-sonner-toast] [data-close-button]{background:var(--gray1)}:where([data-sonner-toast]) :where([data-close-button]):focus-visible{box-shadow:0 4px 12px rgba(0,0,0,.1),0 0 0 2px rgba(0,0,0,.2)}:where([data-sonner-toast]) :where([data-disabled=true]){cursor:not-allowed}[data-sonner-toast]:hover [data-close-button]:hover{background:var(--gray2);border-color:var(--gray5)}:where([data-sonner-toast][data-swiping=true])::before{content:'';position:absolute;left:0;right:0;height:100%;z-index:-1}:where([data-sonner-toast][data-y-position=top][data-swiping=true])::before{bottom:50%;transform:scaleY(3) translateY(50%)}:where([data-sonner-toast][data-y-position=bottom][data-swiping=true])::before{top:50%;transform:scaleY(3) translateY(-50%)}:where([data-sonner-toast][data-swiping=false][data-removed=true])::before{content:'';position:absolute;inset:0;transform:scaleY(2)}:where([data-sonner-toast])::after{content:'';position:absolute;left:0;height:calc(var(--gap) + 1px);bottom:100%;width:100%}:where([data-sonner-toast][data-mounted=true]){--y:translateY(0);opacity:1}:where([data-sonner-toast][data-expanded=false][data-front=false]){--scale:var(--toasts-before) * 0.05 + 1;--y:translateY(calc(var(--lift-amount) * var(--toasts-before))) scale(calc(-1 * var(--scale)));height:var(--front-toast-height)}:where([data-sonner-toast])>*{transition:opacity .4s}:where([data-sonner-toast][data-expanded=false][data-front=false][data-styled=true])>*{opacity:0}:where([data-sonner-toast][data-visible=false]){opacity:0;pointer-events:none}:where([data-sonner-toast][data-mounted=true][data-expanded=true]){--y:translateY(calc(var(--lift) * var(--offset)));height:var(--initial-height)}:where([data-sonner-toast][data-removed=true][data-front=true][data-swipe-out=false]){--y:translateY(calc(var(--lift) * -100%));opacity:0}:where([data-sonner-toast][data-removed=true][data-front=false][data-swipe-out=false][data-expanded=true]){--y:translateY(calc(var(--lift) * var(--offset) + var(--lift) * -100%));opacity:0}:where([data-sonner-toast][data-removed=true][data-front=false][data-swipe-out=false][data-expanded=false]){--y:translateY(40%);opacity:0;transition:transform .5s,opacity .2s}:where([data-sonner-toast][data-removed=true][data-front=false])::before{height:calc(var(--initial-height) + 20%)}[data-sonner-toast][data-swiping=true]{transform:var(--y) translateY(var(--swipe-amount,0));transition:none}[data-sonner-toast][data-swiped=true]{user-select:none}[data-sonner-toast][data-swipe-out=true][data-y-position=bottom],[data-sonner-toast][data-swipe-out=true][data-y-position=top]{animation:swipe-out .2s ease-out forwards}@keyframes swipe-out{from{transform:translateY(calc(var(--lift) * var(--offset) + var(--swipe-amount)));opacity:1}to{transform:translateY(calc(var(--lift) * var(--offset) + var(--swipe-amount) + var(--lift) * -100%));opacity:0}}@media (max-width:600px){[data-sonner-toaster]{position:fixed;--mobile-offset:16px;right:var(--mobile-offset);left:var(--mobile-offset);width:100%}[data-sonner-toaster][dir=rtl]{left:calc(var(--mobile-offset) * -1)}[data-sonner-toaster] [data-sonner-toast]{left:0;right:0;width:calc(100% - var(--mobile-offset) * 2)}[data-sonner-toaster][data-x-position=left]{left:var(--mobile-offset)}[data-sonner-toaster][data-y-position=bottom]{bottom:20px}[data-sonner-toaster][data-y-position=top]{top:20px}[data-sonner-toaster][data-x-position=center]{left:var(--mobile-offset);right:var(--mobile-offset);transform:none}}[data-sonner-toaster][data-theme=light]{--normal-bg:#fff;--normal-border:var(--gray4);--normal-text:var(--gray12);--success-bg:hsl(143, 85%, 96%);--success-border:hsl(145, 92%, 91%);--success-text:hsl(140, 100%, 27%);--info-bg:hsl(208, 100%, 97%);--info-border:hsl(221, 91%, 91%);--info-text:hsl(210, 92%, 45%);--warning-bg:hsl(49, 100%, 97%);--warning-border:hsl(49, 91%, 91%);--warning-text:hsl(31, 92%, 45%);--error-bg:hsl(359, 100%, 97%);--error-border:hsl(359, 100%, 94%);--error-text:hsl(360, 100%, 45%)}[data-sonner-toaster][data-theme=light] [data-sonner-toast][data-invert=true]{--normal-bg:#000;--normal-border:hsl(0, 0%, 20%);--normal-text:var(--gray1)}[data-sonner-toaster][data-theme=dark] [data-sonner-toast][data-invert=true]{--normal-bg:#fff;--normal-border:var(--gray3);--normal-text:var(--gray12)}[data-sonner-toaster][data-theme=dark]{--normal-bg:#000;--normal-border:hsl(0, 0%, 20%);--normal-text:var(--gray1);--success-bg:hsl(150, 100%, 6%);--success-border:hsl(147, 100%, 12%);--success-text:hsl(150, 86%, 65%);--info-bg:hsl(215, 100%, 6%);--info-border:hsl(223, 100%, 12%);--info-text:hsl(216, 87%, 65%);--warning-bg:hsl(64, 100%, 6%);--warning-border:hsl(60, 100%, 12%);--warning-text:hsl(46, 87%, 65%);--error-bg:hsl(358, 76%, 10%);--error-border:hsl(357, 89%, 16%);--error-text:hsl(358, 100%, 81%)}[data-rich-colors=true][data-sonner-toast][data-type=success]{background:var(--success-bg);border-color:var(--success-border);color:var(--success-text)}[data-rich-colors=true][data-sonner-toast][data-type=success] [data-close-button]{background:var(--success-bg);border-color:var(--success-border);color:var(--success-text)}[data-rich-colors=true][data-sonner-toast][data-type=info]{background:var(--info-bg);border-color:var(--info-border);color:var(--info-text)}[data-rich-colors=true][data-sonner-toast][data-type=info] [data-close-button]{background:var(--info-bg);border-color:var(--info-border);color:var(--info-text)}[data-rich-colors=true][data-sonner-toast][data-type=warning]{background:var(--warning-bg);border-color:var(--warning-border);color:var(--warning-text)}[data-rich-colors=true][data-sonner-toast][data-type=warning] [data-close-button]{background:var(--warning-bg);border-color:var(--warning-border);color:var(--warning-text)}[data-rich-colors=true][data-sonner-toast][data-type=error]{background:var(--error-bg);border-color:var(--error-border);color:var(--error-text)}[data-rich-colors=true][data-sonner-toast][data-type=error] [data-close-button]{background:var(--error-bg);border-color:var(--error-border);color:var(--error-text)}.sonner-loading-wrapper{--size:16px;height:var(--size);width:var(--size);position:absolute;inset:0;z-index:10}.sonner-loading-wrapper[data-visible=false]{transform-origin:center;animation:sonner-fade-out .2s ease forwards}.sonner-spinner{position:relative;top:50%;left:50%;height:var(--size);width:var(--size)}.sonner-loading-bar{animation:sonner-spin 1.2s linear infinite;background:var(--gray11);border-radius:6px;height:8%;left:-10%;position:absolute;top:-3.9%;width:24%}.sonner-loading-bar:first-child{animation-delay:-1.2s;transform:rotate(.0001deg) translate(146%)}.sonner-loading-bar:nth-child(2){animation-delay:-1.1s;transform:rotate(30deg) translate(146%)}.sonner-loading-bar:nth-child(3){animation-delay:-1s;transform:rotate(60deg) translate(146%)}.sonner-loading-bar:nth-child(4){animation-delay:-.9s;transform:rotate(90deg) translate(146%)}.sonner-loading-bar:nth-child(5){animation-delay:-.8s;transform:rotate(120deg) translate(146%)}.sonner-loading-bar:nth-child(6){animation-delay:-.7s;transform:rotate(150deg) translate(146%)}.sonner-loading-bar:nth-child(7){animation-delay:-.6s;transform:rotate(180deg) translate(146%)}.sonner-loading-bar:nth-child(8){animation-delay:-.5s;transform:rotate(210deg) translate(146%)}.sonner-loading-bar:nth-child(9){animation-delay:-.4s;transform:rotate(240deg) translate(146%)}.sonner-loading-bar:nth-child(10){animation-delay:-.3s;transform:rotate(270deg) translate(146%)}.sonner-loading-bar:nth-child(11){animation-delay:-.2s;transform:rotate(300deg) translate(146%)}.sonner-loading-bar:nth-child(12){animation-delay:-.1s;transform:rotate(330deg) translate(146%)}@keyframes sonner-fade-in{0%{opacity:0;transform:scale(.8)}100%{opacity:1;transform:scale(1)}}@keyframes sonner-fade-out{0%{opacity:1;transform:scale(1)}100%{opacity:0;transform:scale(.8)}}@keyframes sonner-spin{0%{opacity:1}100%{opacity:.15}}@media (prefers-reduced-motion){.sonner-loading-bar,[data-sonner-toast],[data-sonner-toast]>*{transition:none!important;animation:none!important}}.sonner-loader{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);transform-origin:center;transition:opacity .2s,transform .2s}.sonner-loader[data-visible=false]{opacity:0;transform:scale(.8) translate(-50%,-50%)}");
let Ds = 0;
class jb {
  constructor() {
    rt(this, "subscribers"), rt(this, "toasts"), rt(this, "subscribe", (t) => (this.subscribers.push(t), () => {
      const n = this.subscribers.indexOf(t);
      this.subscribers.splice(n, 1);
    })), rt(this, "publish", (t) => {
      this.subscribers.forEach((n) => n(t));
    }), rt(this, "addToast", (t) => {
      this.publish(t), this.toasts = [...this.toasts, t];
    }), rt(this, "create", (t) => {
      var n;
      const { message: o, ...r } = t, s = typeof t.id == "number" || t.id && ((n = t.id) == null ? void 0 : n.length) > 0 ? t.id : Ds++, a = this.toasts.find((l) => l.id === s), i = t.dismissible === void 0 ? !0 : t.dismissible;
      return a ? this.toasts = this.toasts.map((l) => l.id === s ? (this.publish({ ...l, ...t, id: s, title: o }), {
        ...l,
        ...t,
        id: s,
        dismissible: i,
        title: o
      }) : l) : this.addToast({ title: o, ...r, dismissible: i, id: s }), s;
    }), rt(this, "dismiss", (t) => (t || this.toasts.forEach((n) => {
      this.subscribers.forEach(
        (o) => o({ id: n.id, dismiss: !0 })
      );
    }), this.subscribers.forEach((n) => n({ id: t, dismiss: !0 })), t)), rt(this, "message", (t, n) => this.create({ ...n, message: t, type: "default" })), rt(this, "error", (t, n) => this.create({ ...n, type: "error", message: t })), rt(this, "success", (t, n) => this.create({ ...n, type: "success", message: t })), rt(this, "info", (t, n) => this.create({ ...n, type: "info", message: t })), rt(this, "warning", (t, n) => this.create({ ...n, type: "warning", message: t })), rt(this, "loading", (t, n) => this.create({ ...n, type: "loading", message: t })), rt(this, "promise", (t, n) => {
      if (!n)
        return;
      let o;
      n.loading !== void 0 && (o = this.create({
        ...n,
        promise: t,
        type: "loading",
        message: n.loading,
        description: typeof n.description != "function" ? n.description : void 0
      }));
      const r = t instanceof Promise ? t : t();
      let s = o !== void 0, a;
      const i = r.then(async (u) => {
        if (a = ["resolve", u], Wb(u) && !u.ok) {
          s = !1;
          const c = typeof n.error == "function" ? await n.error(
            `HTTP error! status: ${u.status}`
          ) : n.error, f = typeof n.description == "function" ? (
            // @ts-expect-error
            await n.description(`HTTP error! status: ${u.status}`)
          ) : n.description;
          this.create({ id: o, type: "error", message: c, description: f });
        } else if (n.success !== void 0) {
          s = !1;
          const c = typeof n.success == "function" ? await n.success(u) : n.success, f = typeof n.description == "function" ? await n.description(u) : n.description;
          this.create({ id: o, type: "success", message: c, description: f });
        }
      }).catch(async (u) => {
        if (a = ["reject", u], n.error !== void 0) {
          s = !1;
          const c = typeof n.error == "function" ? await n.error(u) : n.error, f = typeof n.description == "function" ? await n.description(
            u
          ) : n.description;
          this.create({ id: o, type: "error", message: c, description: f });
        }
      }).finally(() => {
        var u;
        s && (this.dismiss(o), o = void 0), (u = n.finally) == null || u.call(n);
      }), l = () => new Promise(
        (u, c) => i.then(
          () => a[0] === "reject" ? c(a[1]) : u(a[1])
        ).catch(c)
      );
      return typeof o != "string" && typeof o != "number" ? { unwrap: l } : Object.assign(o, { unwrap: l });
    }), rt(this, "custom", (t, n) => {
      const o = (n == null ? void 0 : n.id) || Ds++;
      return this.publish({ component: t, id: o, ...n }), o;
    }), this.subscribers = [], this.toasts = [];
  }
}
const dt = new jb();
function Hb(e, t) {
  const n = (t == null ? void 0 : t.id) || Ds++;
  return dt.create({
    message: e,
    id: n,
    type: "default",
    ...t
  }), n;
}
const Wb = (e) => e && typeof e == "object" && "ok" in e && typeof e.ok == "boolean" && "status" in e && typeof e.status == "number", Kb = Hb, Ub = () => dt.toasts, Gb = Object.assign(
  Kb,
  {
    success: dt.success,
    info: dt.info,
    warning: dt.warning,
    error: dt.error,
    custom: dt.custom,
    message: dt.message,
    promise: dt.promise,
    dismiss: dt.dismiss,
    loading: dt.loading
  },
  {
    getHistory: Ub
  }
);
function Ko(e) {
  return e.label !== void 0;
}
function Yb() {
  const e = B(!1);
  return Me(() => {
    const t = () => {
      e.value = document.hidden;
    };
    return document.addEventListener("visibilitychange", t), () => window.removeEventListener("visibilitychange", t);
  }), {
    isDocumentHidden: e
  };
}
const qb = ["aria-live", "data-rich-colors", "data-styled", "data-mounted", "data-promise", "data-removed", "data-visible", "data-y-position", "data-x-position", "data-index", "data-front", "data-swiping", "data-dismissible", "data-type", "data-invert", "data-swipe-out", "data-expanded"], Xb = ["aria-label", "data-disabled"], Ri = 4e3, Jb = 20, Zb = 200, Qb = /* @__PURE__ */ T({
  __name: "Toast",
  props: {
    toast: {},
    toasts: {},
    index: {},
    expanded: { type: Boolean },
    invert: { type: Boolean },
    heights: {},
    gap: {},
    position: {},
    visibleToasts: {},
    expandByDefault: { type: Boolean },
    closeButton: { type: Boolean },
    interacting: { type: Boolean },
    style: {},
    cancelButtonStyle: {},
    actionButtonStyle: {},
    duration: {},
    class: {},
    unstyled: { type: Boolean },
    descriptionClass: {},
    loadingIcon: {},
    classes: {},
    icons: {},
    closeButtonAriaLabel: {},
    pauseWhenPageIsHidden: { type: Boolean },
    cn: { type: Function },
    defaultRichColors: { type: Boolean }
  },
  emits: ["update:heights", "removeToast"],
  setup(e, { emit: t }) {
    const n = e, o = t, r = B(!1), s = B(!1), a = B(!1), i = B(!1), l = B(!1), u = B(0), c = B(0), f = B(
      n.toast.duration || n.duration || Ri
    ), p = B(null), h = B(null), g = O(() => n.index === 0), y = O(() => n.index + 1 <= n.visibleToasts), C = O(() => n.toast.type), v = O(() => n.toast.dismissible !== !1), w = O(() => n.toast.class || ""), m = O(() => n.descriptionClass || ""), x = n.toast.style || {}, A = O(
      () => n.heights.findIndex((D) => D.toastId === n.toast.id) || 0
    ), R = O(() => n.toast.closeButton ?? n.closeButton);
    O(
      () => n.toast.duration || n.duration || Ri
    );
    const I = B(0), H = B(0), k = B(null), j = O(() => n.position.split("-")), K = O(() => j.value[0]), J = O(() => j.value[1]), ge = O(() => typeof n.toast.title != "string"), ce = O(
      () => typeof n.toast.description != "string"
    ), Pe = O(() => n.heights.reduce((D, ve, _e) => _e >= A.value ? D : D + ve.height, 0)), ue = Yb(), ee = O(() => n.toast.invert || n.invert), ne = O(() => C.value === "loading"), he = O(() => A.value * n.gap + Pe.value || 0);
    Se(() => {
      if (!r.value) return;
      const D = h.value, ve = D == null ? void 0 : D.style.height;
      D.style.height = "auto";
      const _e = D.getBoundingClientRect().height;
      D.style.height = ve, c.value = _e;
      let Ee;
      n.heights.find(
        ($e) => $e.toastId === n.toast.id
      ) ? Ee = n.heights.map(
        ($e) => $e.toastId === n.toast.id ? { ...$e, height: _e } : $e
      ) : Ee = [
        {
          toastId: n.toast.id,
          height: _e,
          position: n.toast.position
        },
        ...n.heights
      ], o("update:heights", Ee);
    });
    function je() {
      s.value = !0, u.value = he.value;
      const D = n.heights.filter(
        (ve) => ve.toastId !== n.toast.id
      );
      o("update:heights", D), setTimeout(() => {
        o("removeToast", n.toast);
      }, Zb);
    }
    function ze() {
      var D, ve;
      if (ne.value || !v.value)
        return {};
      je(), (ve = (D = n.toast).onDismiss) == null || ve.call(D, n.toast);
    }
    function Je(D) {
      ne.value || !v.value || (p.value = /* @__PURE__ */ new Date(), u.value = he.value, D.target.setPointerCapture(D.pointerId), D.target.tagName !== "BUTTON" && (a.value = !0, k.value = { x: D.clientX, y: D.clientY }));
    }
    function Mn() {
      var D, ve, _e, Ee, $e;
      if (i.value || !v) return;
      k.value = null;
      const De = Number(
        ((D = h.value) == null ? void 0 : D.style.getPropertyValue("--swipe-amount").replace("px", "")) || 0
      ), Kt = (/* @__PURE__ */ new Date()).getTime() - ((ve = p.value) == null ? void 0 : ve.getTime()), b = Math.abs(De) / Kt;
      if (Math.abs(De) >= Jb || b > 0.11) {
        u.value = he.value, (Ee = (_e = n.toast).onDismiss) == null || Ee.call(_e, n.toast), je(), i.value = !0, l.value = !1;
        return;
      }
      ($e = h.value) == null || $e.style.setProperty("--swipe-amount", "0px"), a.value = !1;
    }
    function sn(D) {
      var ve, _e;
      if (!k.value || !v.value) return;
      const Ee = D.clientY - k.value.y, $e = ((ve = window.getSelection()) == null ? void 0 : ve.toString().length) > 0, De = K.value === "top" ? Math.min(0, Ee) : Math.max(0, Ee);
      Math.abs(De) > 0 && (l.value = !0), !$e && ((_e = h.value) == null || _e.style.setProperty("--swipe-amount", `${De}px`));
    }
    return Me((D) => {
      if (n.toast.promise && C.value === "loading" || n.toast.duration === 1 / 0 || n.toast.type === "loading")
        return;
      let ve;
      const _e = () => {
        if (H.value < I.value) {
          const $e = (/* @__PURE__ */ new Date()).getTime() - I.value;
          f.value = f.value - $e;
        }
        H.value = (/* @__PURE__ */ new Date()).getTime();
      }, Ee = () => {
        f.value !== 1 / 0 && (I.value = (/* @__PURE__ */ new Date()).getTime(), ve = setTimeout(() => {
          var $e, De;
          (De = ($e = n.toast).onAutoClose) == null || De.call($e, n.toast), je();
        }, f.value));
      };
      n.expanded || n.interacting || n.pauseWhenPageIsHidden && ue ? _e() : Ee(), D(() => {
        clearTimeout(ve);
      });
    }), be(
      () => n.toast.delete,
      () => {
        n.toast.delete && je();
      },
      {
        deep: !0
      }
    ), Se(() => {
      if (r.value = !0, h.value) {
        const D = h.value.getBoundingClientRect().height;
        c.value = D;
        const ve = [
          { toastId: n.toast.id, height: D, position: n.toast.position },
          ...n.heights
        ];
        o("update:heights", ve);
      }
    }), Ao(() => {
      if (h.value) {
        const D = n.heights.filter(
          (ve) => ve.toastId !== n.toast.id
        );
        o("update:heights", D);
      }
    }), (D, ve) => {
      var _e, Ee, $e, De, Kt, b, $, M, V, L, F, Y, G, U, N, te, q, Q, se, pe, Te, ye, Ze, Ge, ut, ct, an;
      return _(), oe("li", {
        ref_key: "toastRef",
        ref: h,
        "aria-live": D.toast.important ? "assertive" : "polite",
        "aria-atomic": "true",
        role: "status",
        tabindex: "0",
        "data-sonner-toast": "true",
        class: le(
          D.cn(
            n.class,
            w.value,
            (_e = D.classes) == null ? void 0 : _e.toast,
            (Ee = D.toast.classes) == null ? void 0 : Ee.toast,
            // @ts-ignore
            ($e = D.classes) == null ? void 0 : $e[C.value],
            // @ts-ignore
            (Kt = (De = D.toast) == null ? void 0 : De.classes) == null ? void 0 : Kt[C.value]
          )
        ),
        "data-rich-colors": D.toast.richColors ?? D.defaultRichColors,
        "data-styled": !(D.toast.component || (b = D.toast) != null && b.unstyled || D.unstyled),
        "data-mounted": r.value,
        "data-promise": !!D.toast.promise,
        "data-removed": s.value,
        "data-visible": y.value,
        "data-y-position": K.value,
        "data-x-position": J.value,
        "data-index": D.index,
        "data-front": g.value,
        "data-swiping": a.value,
        "data-dismissible": v.value,
        "data-type": C.value,
        "data-invert": ee.value,
        "data-swipe-out": i.value,
        "data-expanded": !!(D.expanded || D.expandByDefault && r.value),
        style: nt({
          "--index": D.index,
          "--toasts-before": D.index,
          "--z-index": D.toasts.length - D.index,
          "--offset": `${s.value ? u.value : he.value}px`,
          "--initial-height": D.expandByDefault ? "auto" : `${c.value}px`,
          ...D.style,
          ...d(x)
        }),
        onPointerdown: Je,
        onPointerup: Mn,
        onPointermove: sn
      }, [
        R.value && !D.toast.component ? (_(), oe("button", {
          key: 0,
          "aria-label": D.closeButtonAriaLabel || "Close toast",
          "data-disabled": ne.value,
          "data-close-button": "true",
          class: le(D.cn(($ = D.classes) == null ? void 0 : $.closeButton, (V = (M = D.toast) == null ? void 0 : M.classes) == null ? void 0 : V.closeButton)),
          onClick: ze
        }, [
          (L = D.icons) != null && L.close ? (_(), E(_t((F = D.icons) == null ? void 0 : F.close), { key: 0 })) : P(D.$slots, "close-icon", { key: 1 })
        ], 10, Xb)) : Be("", !0),
        D.toast.component ? (_(), E(_t(D.toast.component), z({ key: 1 }, D.toast.componentProps, { onCloseToast: ze }), null, 16)) : (_(), oe(ke, { key: 2 }, [
          C.value !== "default" || D.toast.icon || D.toast.promise ? (_(), oe("div", {
            key: 0,
            "data-icon": "",
            class: le(D.cn((Y = D.classes) == null ? void 0 : Y.icon, (U = (G = D.toast) == null ? void 0 : G.classes) == null ? void 0 : U.icon))
          }, [
            (D.toast.promise || C.value === "loading") && !D.toast.icon ? P(D.$slots, "loading-icon", { key: 0 }) : Be("", !0),
            D.toast.icon ? (_(), E(_t(D.toast.icon), { key: 1 })) : (_(), oe(ke, { key: 2 }, [
              C.value === "success" ? P(D.$slots, "success-icon", { key: 0 }) : C.value === "error" ? P(D.$slots, "error-icon", { key: 1 }) : C.value === "warning" ? P(D.$slots, "warning-icon", { key: 2 }) : C.value === "info" ? P(D.$slots, "info-icon", { key: 3 }) : Be("", !0)
            ], 64))
          ], 2)) : Be("", !0),
          Z("div", {
            "data-content": "",
            class: le(D.cn((N = D.classes) == null ? void 0 : N.content, (q = (te = D.toast) == null ? void 0 : te.classes) == null ? void 0 : q.content))
          }, [
            Z("div", {
              "data-title": "",
              class: le(D.cn((Q = D.classes) == null ? void 0 : Q.title, (se = D.toast.classes) == null ? void 0 : se.title))
            }, [
              ge.value ? (_(), E(_t(D.toast.title), me(z({ key: 0 }, D.toast.componentProps)), null, 16)) : (_(), oe(ke, { key: 1 }, [
                At(xt(D.toast.title), 1)
              ], 64))
            ], 2),
            D.toast.description ? (_(), oe("div", {
              key: 0,
              "data-description": "",
              class: le(
                D.cn(
                  D.descriptionClass,
                  m.value,
                  (pe = D.classes) == null ? void 0 : pe.description,
                  (Te = D.toast.classes) == null ? void 0 : Te.description
                )
              )
            }, [
              ce.value ? (_(), E(_t(D.toast.description), me(z({ key: 0 }, D.toast.componentProps)), null, 16)) : (_(), oe(ke, { key: 1 }, [
                At(xt(D.toast.description), 1)
              ], 64))
            ], 2)) : Be("", !0)
          ], 2),
          D.toast.cancel ? (_(), oe("button", {
            key: 1,
            style: nt(D.toast.cancelButtonStyle || D.cancelButtonStyle),
            class: le(D.cn((ye = D.classes) == null ? void 0 : ye.cancelButton, (Ze = D.toast.classes) == null ? void 0 : Ze.cancelButton)),
            "data-button": "",
            "data-cancel": "",
            onClick: ve[0] || (ve[0] = (ln) => {
              var We, Qe;
              d(Ko)(D.toast.cancel) && v.value && ((Qe = (We = D.toast.cancel).onClick) == null || Qe.call(We, ln), je());
            })
          }, xt(d(Ko)(D.toast.cancel) ? (Ge = D.toast.cancel) == null ? void 0 : Ge.label : D.toast.cancel), 7)) : Be("", !0),
          D.toast.action ? (_(), oe("button", {
            key: 2,
            style: nt(D.toast.actionButtonStyle || D.actionButtonStyle),
            class: le(D.cn((ut = D.classes) == null ? void 0 : ut.actionButton, (ct = D.toast.classes) == null ? void 0 : ct.actionButton)),
            "data-button": "",
            "data-action": "",
            onClick: ve[1] || (ve[1] = (ln) => {
              var We, Qe;
              d(Ko)(D.toast.action) && (ln.defaultPrevented || ((Qe = (We = D.toast.action).onClick) == null || Qe.call(We, ln), !ln.defaultPrevented && je()));
            })
          }, xt(d(Ko)(D.toast.action) ? (an = D.toast.action) == null ? void 0 : an.label : D.toast.action), 7)) : Be("", !0)
        ], 64))
      ], 46, qb);
    };
  }
}), Do = (e, t) => {
  const n = e.__vccOpts || e;
  for (const [o, r] of t)
    n[o] = r;
  return n;
}, e0 = {}, t0 = {
  xmlns: "http://www.w3.org/2000/svg",
  width: "12",
  height: "12",
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  "stoke-width": "1.5",
  "stroke-linecap": "round",
  "stroke-linejoin": "round"
};
function n0(e, t) {
  return _(), oe("svg", t0, t[0] || (t[0] = [
    Z("line", {
      x1: "18",
      y1: "6",
      x2: "6",
      y2: "18"
    }, null, -1),
    Z("line", {
      x1: "6",
      y1: "6",
      x2: "18",
      y2: "18"
    }, null, -1)
  ]));
}
const o0 = /* @__PURE__ */ Do(e0, [["render", n0]]), r0 = ["data-visible"], s0 = { class: "sonner-spinner" }, a0 = /* @__PURE__ */ T({
  __name: "Loader",
  props: {
    visible: { type: Boolean }
  },
  setup(e) {
    const t = Array(12).fill(0);
    return (n, o) => (_(), oe("div", {
      class: "sonner-loading-wrapper",
      "data-visible": n.visible
    }, [
      Z("div", s0, [
        (_(!0), oe(ke, null, wo(d(t), (r) => (_(), oe("div", {
          key: `spinner-bar-${r}`,
          class: "sonner-loading-bar"
        }))), 128))
      ])
    ], 8, r0));
  }
}), i0 = {}, l0 = {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 20 20",
  fill: "currentColor",
  height: "20",
  width: "20"
};
function u0(e, t) {
  return _(), oe("svg", l0, t[0] || (t[0] = [
    Z("path", {
      "fill-rule": "evenodd",
      d: "M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z",
      "clip-rule": "evenodd"
    }, null, -1)
  ]));
}
const c0 = /* @__PURE__ */ Do(i0, [["render", u0]]), d0 = {}, f0 = {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 20 20",
  fill: "currentColor",
  height: "20",
  width: "20"
};
function p0(e, t) {
  return _(), oe("svg", f0, t[0] || (t[0] = [
    Z("path", {
      "fill-rule": "evenodd",
      d: "M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z",
      "clip-rule": "evenodd"
    }, null, -1)
  ]));
}
const h0 = /* @__PURE__ */ Do(d0, [["render", p0]]), m0 = {}, g0 = {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 24 24",
  fill: "currentColor",
  height: "20",
  width: "20"
};
function v0(e, t) {
  return _(), oe("svg", g0, t[0] || (t[0] = [
    Z("path", {
      "fill-rule": "evenodd",
      d: "M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z",
      "clip-rule": "evenodd"
    }, null, -1)
  ]));
}
const y0 = /* @__PURE__ */ Do(m0, [["render", v0]]), b0 = {}, w0 = {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 20 20",
  fill: "currentColor",
  height: "20",
  width: "20"
};
function _0(e, t) {
  return _(), oe("svg", w0, t[0] || (t[0] = [
    Z("path", {
      "fill-rule": "evenodd",
      d: "M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z",
      "clip-rule": "evenodd"
    }, null, -1)
  ]));
}
const x0 = /* @__PURE__ */ Do(b0, [["render", _0]]), C0 = ["aria-label"], S0 = ["dir", "data-theme", "data-rich-colors", "data-y-position", "data-x-position", "data-lifted"], $0 = 3, Ii = "32px", T0 = 356, P0 = 14, E0 = typeof window < "u" && typeof document < "u";
function A0(...e) {
  return e.filter(Boolean).join(" ");
}
const O0 = /* @__PURE__ */ T({
  name: "Toaster",
  inheritAttrs: !1,
  __name: "Toaster",
  props: {
    invert: { type: Boolean, default: !1 },
    theme: { default: "light" },
    position: { default: "bottom-right" },
    hotkey: { default: () => ["altKey", "KeyT"] },
    richColors: { type: Boolean, default: !1 },
    expand: { type: Boolean, default: !1 },
    duration: {},
    gap: { default: P0 },
    visibleToasts: { default: $0 },
    closeButton: { type: Boolean, default: !1 },
    toastOptions: { default: () => ({}) },
    class: { default: "" },
    style: { default: () => ({}) },
    offset: { default: Ii },
    dir: { default: "auto" },
    icons: {},
    containerAriaLabel: { default: "Notifications" },
    pauseWhenPageIsHidden: { type: Boolean, default: !1 },
    cn: { type: Function, default: A0 }
  },
  setup(e) {
    const t = e;
    function n() {
      if (typeof window > "u" || typeof document > "u") return "ltr";
      const m = document.documentElement.getAttribute("dir");
      return m === "auto" || !m ? window.getComputedStyle(document.documentElement).direction : m;
    }
    const o = Od(), r = B([]), s = O(() => (m, x) => r.value.filter(
      (A) => !A.position && x === 0 || A.position === m
    )), a = O(() => {
      const m = r.value.filter((x) => x.position).map((x) => x.position);
      return m.length > 0 ? Array.from(new Set([t.position].concat(m))) : [t.position];
    }), i = B([]), l = B(!1), u = B(!1), c = B(
      t.theme !== "system" ? t.theme : typeof window < "u" && window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light"
    ), f = B(null), p = B(null), h = B(!1), g = t.hotkey.join("+").replace(/Key/g, "").replace(/Digit/g, "");
    function y(m) {
      var x;
      (x = r.value.find((A) => A.id === m.id)) != null && x.delete || dt.dismiss(m.id), r.value = r.value.filter(({ id: A }) => A !== m.id);
    }
    function C(m) {
      var x, A;
      h.value && !((A = (x = m.currentTarget) == null ? void 0 : x.contains) != null && A.call(x, m.relatedTarget)) && (h.value = !1, p.value && (p.value.focus({ preventScroll: !0 }), p.value = null));
    }
    function v(m) {
      m.target instanceof HTMLElement && m.target.dataset.dismissible === "false" || h.value || (h.value = !0, p.value = m.relatedTarget);
    }
    function w(m) {
      m.target && m.target instanceof HTMLElement && m.target.dataset.dismissible === "false" || (u.value = !0);
    }
    return Me((m) => {
      const x = dt.subscribe((A) => {
        if (A.dismiss) {
          r.value = r.value.map(
            (R) => R.id === A.id ? { ...R, delete: !0 } : R
          );
          return;
        }
        Le(() => {
          const R = r.value.findIndex(
            (I) => I.id === A.id
          );
          R !== -1 ? r.value = [
            ...r.value.slice(0, R),
            { ...r.value[R], ...A },
            ...r.value.slice(R + 1)
          ] : r.value = [A, ...r.value];
        });
      });
      m(x);
    }), be(
      () => t.theme,
      (m) => {
        if (m !== "system") {
          c.value = m;
          return;
        }
        if (m === "system" && (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? c.value = "dark" : c.value = "light"), typeof window > "u") return;
        const x = window.matchMedia("(prefers-color-scheme: dark)");
        try {
          x.addEventListener("change", ({ matches: A }) => {
            A ? c.value = "dark" : c.value = "light";
          });
        } catch {
          x.addListener(({ matches: A }) => {
            try {
              A ? c.value = "dark" : c.value = "light";
            } catch (R) {
              console.error(R);
            }
          });
        }
      }
    ), Me(() => {
      f.value && p.value && (p.value.focus({ preventScroll: !0 }), p.value = null, h.value = !1);
    }), Me(() => {
      r.value.length <= 1 && (l.value = !1);
    }), Me((m) => {
      function x(A) {
        const R = t.hotkey.every(
          (k) => A[k] || A.code === k
        ), I = Array.isArray(f.value) ? f.value[0] : f.value;
        R && (l.value = !0, I == null || I.focus());
        const H = document.activeElement === f.value || (I == null ? void 0 : I.contains(document.activeElement));
        A.code === "Escape" && H && (l.value = !1);
      }
      E0 && (document.addEventListener("keydown", x), m(() => {
        document.removeEventListener("keydown", x);
      }));
    }), (m, x) => (_(), oe("section", {
      "aria-label": `${m.containerAriaLabel} ${d(g)}`,
      tabIndex: -1,
      "aria-live": "polite",
      "aria-relevant": "additions text",
      "aria-atomic": "false"
    }, [
      (_(!0), oe(ke, null, wo(a.value, (A, R) => {
        var I;
        return _(), oe("ol", z({
          key: A,
          ref_for: !0,
          ref_key: "listRef",
          ref: f,
          "data-sonner-toaster": "",
          class: t.class,
          dir: m.dir === "auto" ? n() : m.dir,
          tabIndex: -1,
          "data-theme": m.theme,
          "data-rich-colors": m.richColors,
          "data-y-position": A.split("-")[0],
          "data-x-position": A.split("-")[1],
          "data-lifted": l.value && r.value.length > 1 && !m.expand,
          style: {
            "--front-toast-height": `${(I = i.value[0]) == null ? void 0 : I.height}px`,
            "--offset": typeof m.offset == "number" ? `${m.offset}px` : m.offset || Ii,
            "--width": `${T0}px`,
            "--gap": `${m.gap}px`,
            ...m.style,
            ...d(o).style
          }
        }, m.$attrs, {
          onBlur: C,
          onFocus: v,
          onMouseenter: x[1] || (x[1] = () => l.value = !0),
          onMousemove: x[2] || (x[2] = () => l.value = !0),
          onMouseleave: x[3] || (x[3] = () => {
            u.value || (l.value = !1);
          }),
          onPointerdown: w,
          onPointerup: x[4] || (x[4] = () => u.value = !1)
        }), [
          (_(!0), oe(ke, null, wo(s.value(A, R), (H, k) => {
            var j, K, J, ge, ce, Pe, ue, ee, ne;
            return _(), E(Qb, {
              key: H.id,
              heights: i.value.filter((he) => he.position === H.position),
              icons: m.icons,
              index: k,
              toast: H,
              defaultRichColors: m.richColors,
              duration: ((j = m.toastOptions) == null ? void 0 : j.duration) ?? m.duration,
              class: le(((K = m.toastOptions) == null ? void 0 : K.class) ?? ""),
              descriptionClass: (J = m.toastOptions) == null ? void 0 : J.descriptionClass,
              invert: m.invert,
              visibleToasts: m.visibleToasts,
              closeButton: ((ge = m.toastOptions) == null ? void 0 : ge.closeButton) ?? m.closeButton,
              interacting: u.value,
              position: A,
              style: nt((ce = m.toastOptions) == null ? void 0 : ce.style),
              unstyled: (Pe = m.toastOptions) == null ? void 0 : Pe.unstyled,
              classes: (ue = m.toastOptions) == null ? void 0 : ue.classes,
              cancelButtonStyle: (ee = m.toastOptions) == null ? void 0 : ee.cancelButtonStyle,
              actionButtonStyle: (ne = m.toastOptions) == null ? void 0 : ne.actionButtonStyle,
              toasts: r.value.filter((he) => he.position === H.position),
              expandByDefault: m.expand,
              gap: m.gap,
              expanded: l.value,
              pauseWhenPageIsHidden: m.pauseWhenPageIsHidden,
              cn: m.cn,
              "onUpdate:heights": x[0] || (x[0] = (he) => {
                i.value = he;
              }),
              onRemoveToast: y
            }, {
              "close-icon": S(() => [
                P(m.$slots, "close-icon", {}, () => [
                  W(o0)
                ])
              ]),
              "loading-icon": S(() => [
                P(m.$slots, "loading-icon", {}, () => [
                  W(a0, {
                    visible: H.type === "loading"
                  }, null, 8, ["visible"])
                ])
              ]),
              "success-icon": S(() => [
                P(m.$slots, "success-icon", {}, () => [
                  W(c0)
                ])
              ]),
              "error-icon": S(() => [
                P(m.$slots, "error-icon", {}, () => [
                  W(x0)
                ])
              ]),
              "warning-icon": S(() => [
                P(m.$slots, "warning-icon", {}, () => [
                  W(y0)
                ])
              ]),
              "info-icon": S(() => [
                P(m.$slots, "info-icon", {}, () => [
                  W(h0)
                ])
              ]),
              _: 2
            }, 1032, ["heights", "icons", "index", "toast", "defaultRichColors", "duration", "class", "descriptionClass", "invert", "visibleToasts", "closeButton", "interacting", "position", "style", "unstyled", "classes", "cancelButtonStyle", "actionButtonStyle", "toasts", "expandByDefault", "gap", "expanded", "pauseWhenPageIsHidden", "cn"]);
          }), 128))
        ], 16, S0);
      }), 128))
    ], 8, C0));
  }
}), B0 = /* @__PURE__ */ T({
  __name: "Toaster",
  props: {
    invert: { type: Boolean },
    theme: {},
    position: {},
    hotkey: {},
    richColors: { type: Boolean },
    expand: { type: Boolean },
    duration: {},
    gap: {},
    visibleToasts: {},
    closeButton: { type: Boolean },
    toastOptions: {},
    class: {},
    style: {},
    offset: {},
    dir: {},
    icons: {},
    containerAriaLabel: {},
    pauseWhenPageIsHidden: { type: Boolean },
    cn: { type: Function }
  },
  setup(e) {
    const t = e;
    return Se(() => {
      globalThis.toast = Gb;
    }), (n, o) => (_(), E(d(O0), z({ class: "toaster group" }, t, { "toast-options": {
      classes: {
        toast: "group toast group-[.toaster]:bg-background group-[.toaster]:text-foreground group-[.toaster]:border-border group-[.toaster]:shadow-lg",
        description: "group-[.toast]:text-muted-foreground",
        actionButton: "group-[.toast]:bg-primary group-[.toast]:text-primary-foreground",
        cancelButton: "group-[.toast]:bg-muted group-[.toast]:text-muted-foreground",
        error: "[&>div>svg]:fill-unraid-red-500",
        warning: "[&>div>svg]:fill-yellow-500",
        info: "[&>div>svg]:fill-blue-500"
      }
    } }), null, 16));
  }
}), k0 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  Badge: Gf,
  Bar: ky,
  BrandButton: Ap,
  BrandLoading: Dp,
  BrandLogo: Vp,
  BrandLogoConnect: Wp,
  Button: au,
  CardWrapper: Up,
  DropdownMenu: iy,
  DropdownMenuCheckboxItem: vy,
  DropdownMenuContent: by,
  DropdownMenuGroup: wy,
  DropdownMenuItem: _y,
  DropdownMenuLabel: xy,
  DropdownMenuRadioGroup: Cy,
  DropdownMenuRadioItem: $y,
  DropdownMenuSeparator: Ty,
  DropdownMenuShortcut: Py,
  DropdownMenuSub: Ey,
  DropdownMenuSubContent: Ay,
  DropdownMenuSubTrigger: Oy,
  DropdownMenuTrigger: By,
  Error: Ly,
  Input: zy,
  Label: jy,
  Lightswitch: ib,
  PageContainer: Gp,
  ScrollArea: bb,
  ScrollBar: yc,
  Select: lb,
  SelectContent: ub,
  SelectGroup: cb,
  SelectItem: fb,
  SelectItemText: pb,
  SelectLabel: hb,
  SelectScrollDownButton: gc,
  SelectScrollUpButton: vc,
  SelectSeparator: mb,
  SelectTrigger: gb,
  SelectValue: vb,
  Sheet: _b,
  SheetClose: Cb,
  SheetContent: $b,
  SheetDescription: Eb,
  SheetFooter: Ab,
  SheetHeader: Tb,
  SheetTitle: Pb,
  SheetTrigger: xb,
  Spinner: ac,
  Switch: yb,
  Tabs: Ob,
  TabsContent: Db,
  TabsList: Mb,
  TabsTrigger: kb,
  Toaster: B0,
  Tooltip: Rb,
  TooltipContent: Ib,
  TooltipProvider: Fb,
  TooltipTrigger: Lb
}, Symbol.toStringTag, { value: "Module" })), M0 = new RegExp("\\p{Lu}", "gu"), D0 = new RegExp("-\\p{Ll}", "gu"), bc = (e, t = !0) => {
  const n = e.replace(M0, (o) => `-${o.toLowerCase()}`);
  if (t)
    return n;
  if (n.startsWith("-"))
    return n.slice(1);
};
bc.reverse = (e) => e.replace(D0, (t) => t.slice(1).toUpperCase());
function F0(e = {}) {
  const { namePrefix: t = "uui", pathToSharedCss: n = "./src/styles/index.css" } = e;
  Object.entries(k0).forEach(([o, r]) => {
    r.styles ?? (r.styles = []), r.styles.unshift(`@import "${n}"`);
    let s = bc(o);
    s || (console.log("[register components] Could not translate component name to kebab-case:", o), s = o), s = t + s, customElements.define(s, /* @__PURE__ */ Rf(r));
  });
}
export {
  F0 as registerAllComponents
};
//# sourceMappingURL=register.D9MKs8Co.js.map
