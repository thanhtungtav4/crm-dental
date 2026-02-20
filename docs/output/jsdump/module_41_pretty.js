({
    41: function (e, t, n) {
        "use strict";
        n.d(t, "v", function () {
            return p;
        }),
            n.d(t, "o", function () {
                return f;
            }),
            n.d(t, "n", function () {
                return g;
            }),
            n.d(t, "r", function () {
                return v;
            }),
            n.d(t, "p", function () {
                return E;
            }),
            n.d(t, "j", function () {
                return T;
            }),
            n.d(t, "i", function () {
                return C;
            }),
            n.d(t, "u", function () {
                return b;
            }),
            n.d(t, "t", function () {
                return A;
            }),
            n.d(t, "s", function () {
                return y;
            }),
            n.d(t, "q", function () {
                return O;
            }),
            n.d(t, "l", function () {
                return L;
            }),
            n.d(t, "m", function () {
                return N;
            }),
            n.d(t, "g", function () {
                return S;
            }),
            n.d(t, "k", function () {
                return M;
            }),
            n.d(t, "f", function () {
                return P;
            }),
            n.d(t, "e", function () {
                return D;
            }),
            n.d(t, "b", function () {
                return R;
            }),
            n.d(t, "a", function () {
                return _;
            }),
            n.d(t, "d", function () {
                return w;
            }),
            n.d(t, "h", function () {
                return I;
            });
        var a = n(5),
            r = n(4),
            i = n(2),
            o = n(142),
            c = n(1),
            l = n(3),
            u = n(9),
            s = n(32),
            h = n(6);
        function d() {
            d = function () {
                return e;
            };
            var e = {},
                t = Object.prototype,
                n = t.hasOwnProperty,
                a =
                    Object.defineProperty ||
                    function (e, t, n) {
                        e[t] = n.value;
                    },
                r = "function" == typeof Symbol ? Symbol : {},
                i = r.iterator || "@@iterator",
                o = r.asyncIterator || "@@asyncIterator",
                c = r.toStringTag || "@@toStringTag";
            function l(e, t, n) {
                return (
                    Object.defineProperty(e, t, {
                        value: n,
                        enumerable: !0,
                        configurable: !0,
                        writable: !0,
                    }),
                    e[t]
                );
            }
            try {
                l({}, "");
            } catch (P) {
                l = function (e, t, n) {
                    return (e[t] = n);
                };
            }
            function u(e, t, n, r) {
                var i = t && t.prototype instanceof m ? t : m,
                    o = Object.create(i.prototype),
                    c = new N(r || []);
                return a(o, "_invoke", { value: A(e, n, c) }), o;
            }
            function s(e, t, n) {
                try {
                    return { type: "normal", arg: e.call(t, n) };
                } catch (P) {
                    return { type: "throw", arg: P };
                }
            }
            e.wrap = u;
            var h = {};
            function m() {}
            function p() {}
            function f() {}
            var g = {};
            l(g, i, function () {
                return this;
            });
            var v = Object.getPrototypeOf,
                E = v && v(v(S([])));
            E && E !== t && n.call(E, i) && (g = E);
            var T = (f.prototype = m.prototype = Object.create(g));
            function C(e) {
                ["next", "throw", "return"].forEach(function (t) {
                    l(e, t, function (e) {
                        return this._invoke(t, e);
                    });
                });
            }
            function b(e, t) {
                var r;
                a(this, "_invoke", {
                    value: function (a, i) {
                        function o() {
                            return new t(function (r, o) {
                                !(function a(r, i, o, c) {
                                    var l = s(e[r], e, i);
                                    if ("throw" !== l.type) {
                                        var u = l.arg,
                                            h = u.value;
                                        return h &&
                                            "object" == typeof h &&
                                            n.call(h, "__await")
                                            ? t.resolve(h.__await).then(
                                                  function (e) {
                                                      a("next", e, o, c);
                                                  },
                                                  function (e) {
                                                      a("throw", e, o, c);
                                                  },
                                              )
                                            : t.resolve(h).then(
                                                  function (e) {
                                                      (u.value = e), o(u);
                                                  },
                                                  function (e) {
                                                      return a(
                                                          "throw",
                                                          e,
                                                          o,
                                                          c,
                                                      );
                                                  },
                                              );
                                    }
                                    c(l.arg);
                                })(a, i, r, o);
                            });
                        }
                        return (r = r ? r.then(o, o) : o());
                    },
                });
            }
            function A(e, t, n) {
                var a = "suspendedStart";
                return function (r, i) {
                    if ("executing" === a)
                        throw new Error("Generator is already running");
                    if ("completed" === a) {
                        if ("throw" === r) throw i;
                        return M();
                    }
                    for (n.method = r, n.arg = i; ; ) {
                        var o = n.delegate;
                        if (o) {
                            var c = y(o, n);
                            if (c) {
                                if (c === h) continue;
                                return c;
                            }
                        }
                        if ("next" === n.method) n.sent = n._sent = n.arg;
                        else if ("throw" === n.method) {
                            if ("suspendedStart" === a)
                                throw ((a = "completed"), n.arg);
                            n.dispatchException(n.arg);
                        } else
                            "return" === n.method && n.abrupt("return", n.arg);
                        a = "executing";
                        var l = s(e, t, n);
                        if ("normal" === l.type) {
                            if (
                                ((a = n.done ? "completed" : "suspendedYield"),
                                l.arg === h)
                            )
                                continue;
                            return { value: l.arg, done: n.done };
                        }
                        "throw" === l.type &&
                            ((a = "completed"),
                            (n.method = "throw"),
                            (n.arg = l.arg));
                    }
                };
            }
            function y(e, t) {
                var n = t.method,
                    a = e.iterator[n];
                if (void 0 === a)
                    return (
                        (t.delegate = null),
                        ("throw" === n &&
                            e.iterator.return &&
                            ((t.method = "return"),
                            (t.arg = void 0),
                            y(e, t),
                            "throw" === t.method)) ||
                            ("return" !== n &&
                                ((t.method = "throw"),
                                (t.arg = new TypeError(
                                    "The iterator does not provide a '" +
                                        n +
                                        "' method",
                                )))),
                        h
                    );
                var r = s(a, e.iterator, t.arg);
                if ("throw" === r.type)
                    return (
                        (t.method = "throw"),
                        (t.arg = r.arg),
                        (t.delegate = null),
                        h
                    );
                var i = r.arg;
                return i
                    ? i.done
                        ? ((t[e.resultName] = i.value),
                          (t.next = e.nextLoc),
                          "return" !== t.method &&
                              ((t.method = "next"), (t.arg = void 0)),
                          (t.delegate = null),
                          h)
                        : i
                    : ((t.method = "throw"),
                      (t.arg = new TypeError(
                          "iterator result is not an object",
                      )),
                      (t.delegate = null),
                      h);
            }
            function O(e) {
                var t = { tryLoc: e[0] };
                1 in e && (t.catchLoc = e[1]),
                    2 in e && ((t.finallyLoc = e[2]), (t.afterLoc = e[3])),
                    this.tryEntries.push(t);
            }
            function L(e) {
                var t = e.completion || {};
                (t.type = "normal"), delete t.arg, (e.completion = t);
            }
            function N(e) {
                (this.tryEntries = [{ tryLoc: "root" }]),
                    e.forEach(O, this),
                    this.reset(!0);
            }
            function S(e) {
                if (e) {
                    var t = e[i];
                    if (t) return t.call(e);
                    if ("function" == typeof e.next) return e;
                    if (!isNaN(e.length)) {
                        var a = -1,
                            r = function t() {
                                for (; ++a < e.length; )
                                    if (n.call(e, a))
                                        return (
                                            (t.value = e[a]), (t.done = !1), t
                                        );
                                return (t.value = void 0), (t.done = !0), t;
                            };
                        return (r.next = r);
                    }
                }
                return { next: M };
            }
            function M() {
                return { value: void 0, done: !0 };
            }
            return (
                (p.prototype = f),
                a(T, "constructor", { value: f, configurable: !0 }),
                a(f, "constructor", { value: p, configurable: !0 }),
                (p.displayName = l(f, c, "GeneratorFunction")),
                (e.isGeneratorFunction = function (e) {
                    var t = "function" == typeof e && e.constructor;
                    return (
                        !!t &&
                        (t === p ||
                            "GeneratorFunction" === (t.displayName || t.name))
                    );
                }),
                (e.mark = function (e) {
                    return (
                        Object.setPrototypeOf
                            ? Object.setPrototypeOf(e, f)
                            : ((e.__proto__ = f), l(e, c, "GeneratorFunction")),
                        (e.prototype = Object.create(T)),
                        e
                    );
                }),
                (e.awrap = function (e) {
                    return { __await: e };
                }),
                C(b.prototype),
                l(b.prototype, o, function () {
                    return this;
                }),
                (e.AsyncIterator = b),
                (e.async = function (t, n, a, r, i) {
                    void 0 === i && (i = Promise);
                    var o = new b(u(t, n, a, r), i);
                    return e.isGeneratorFunction(n)
                        ? o
                        : o.next().then(function (e) {
                              return e.done ? e.value : o.next();
                          });
                }),
                C(T),
                l(T, c, "Generator"),
                l(T, i, function () {
                    return this;
                }),
                l(T, "toString", function () {
                    return "[object Generator]";
                }),
                (e.keys = function (e) {
                    var t = Object(e),
                        n = [];
                    for (var a in t) n.push(a);
                    return (
                        n.reverse(),
                        function e() {
                            for (; n.length; ) {
                                var a = n.pop();
                                if (a in t)
                                    return (e.value = a), (e.done = !1), e;
                            }
                            return (e.done = !0), e;
                        }
                    );
                }),
                (e.values = S),
                (N.prototype = {
                    constructor: N,
                    reset: function (e) {
                        if (
                            ((this.prev = 0),
                            (this.next = 0),
                            (this.sent = this._sent = void 0),
                            (this.done = !1),
                            (this.delegate = null),
                            (this.method = "next"),
                            (this.arg = void 0),
                            this.tryEntries.forEach(L),
                            !e)
                        )
                            for (var t in this)
                                "t" === t.charAt(0) &&
                                    n.call(this, t) &&
                                    !isNaN(+t.slice(1)) &&
                                    (this[t] = void 0);
                    },
                    stop: function () {
                        this.done = !0;
                        var e = this.tryEntries[0].completion;
                        if ("throw" === e.type) throw e.arg;
                        return this.rval;
                    },
                    dispatchException: function (e) {
                        if (this.done) throw e;
                        var t = this;
                        function a(n, a) {
                            return (
                                (o.type = "throw"),
                                (o.arg = e),
                                (t.next = n),
                                a && ((t.method = "next"), (t.arg = void 0)),
                                !!a
                            );
                        }
                        for (var r = this.tryEntries.length - 1; r >= 0; --r) {
                            var i = this.tryEntries[r],
                                o = i.completion;
                            if ("root" === i.tryLoc) return a("end");
                            if (i.tryLoc <= this.prev) {
                                var c = n.call(i, "catchLoc"),
                                    l = n.call(i, "finallyLoc");
                                if (c && l) {
                                    if (this.prev < i.catchLoc)
                                        return a(i.catchLoc, !0);
                                    if (this.prev < i.finallyLoc)
                                        return a(i.finallyLoc);
                                } else if (c) {
                                    if (this.prev < i.catchLoc)
                                        return a(i.catchLoc, !0);
                                } else {
                                    if (!l)
                                        throw new Error(
                                            "try statement without catch or finally",
                                        );
                                    if (this.prev < i.finallyLoc)
                                        return a(i.finallyLoc);
                                }
                            }
                        }
                    },
                    abrupt: function (e, t) {
                        for (var a = this.tryEntries.length - 1; a >= 0; --a) {
                            var r = this.tryEntries[a];
                            if (
                                r.tryLoc <= this.prev &&
                                n.call(r, "finallyLoc") &&
                                this.prev < r.finallyLoc
                            ) {
                                var i = r;
                                break;
                            }
                        }
                        i &&
                            ("break" === e || "continue" === e) &&
                            i.tryLoc <= t &&
                            t <= i.finallyLoc &&
                            (i = null);
                        var o = i ? i.completion : {};
                        return (
                            (o.type = e),
                            (o.arg = t),
                            i
                                ? ((this.method = "next"),
                                  (this.next = i.finallyLoc),
                                  h)
                                : this.complete(o)
                        );
                    },
                    complete: function (e, t) {
                        if ("throw" === e.type) throw e.arg;
                        return (
                            "break" === e.type || "continue" === e.type
                                ? (this.next = e.arg)
                                : "return" === e.type
                                  ? ((this.rval = this.arg = e.arg),
                                    (this.method = "return"),
                                    (this.next = "end"))
                                  : "normal" === e.type && t && (this.next = t),
                            h
                        );
                    },
                    finish: function (e) {
                        for (var t = this.tryEntries.length - 1; t >= 0; --t) {
                            var n = this.tryEntries[t];
                            if (n.finallyLoc === e)
                                return (
                                    this.complete(n.completion, n.afterLoc),
                                    L(n),
                                    h
                                );
                        }
                    },
                    catch: function (e) {
                        for (var t = this.tryEntries.length - 1; t >= 0; --t) {
                            var n = this.tryEntries[t];
                            if (n.tryLoc === e) {
                                var a = n.completion;
                                if ("throw" === a.type) {
                                    var r = a.arg;
                                    L(n);
                                }
                                return r;
                            }
                        }
                        throw new Error("illegal catch attempt");
                    },
                    delegateYield: function (e, t, n) {
                        return (
                            (this.delegate = {
                                iterator: S(e),
                                resultName: t,
                                nextLoc: n,
                            }),
                            "next" === this.method && (this.arg = void 0),
                            h
                        );
                    },
                }),
                e
            );
        }
        var m = {
                stringeeVisible: !1,
                stringeeClient: null,
                stringeeAuthen: { message: "Not connect", status: !1 },
                stringeeSignalingState: { code: null, reason: null },
                stringeeInComingCall: !1,
                stringeeEndCall: { status: !1, isAnswered: !1 },
                stringeeCallInfo: { status: null, data: null },
                patientInfo: null,
                saveCallCenter: null,
                numbersCallCenter: "",
                isCallFromMissedCall: !1,
                isCallFromCustomerCare: !1,
                isCallFromCustomerCareSuccess: !1,
                forceRenderMissedCall: {},
                callingInfo: { phone: null, content: null },
                isAgent: !1,
                patientCaredData: {},
                forceRenderCustomerCare: {},
                countMissedCall: { countMissedCall: 0, maxMissedCall: 0 },
                isNewCall: !1,
                careSoftCallId: null,
            },
            p = function (e) {
                return function (t) {
                    t({
                        type: "callCenter/SET_STRINGEE_VISIBLE",
                        payload: { stringeeVisible: e },
                    });
                };
            },
            f = function () {
                return function (e) {
                    e({
                        type: "callCenter/SET_RERENDER_MISSED_CALL",
                        payload: { forceRenderMissedCall: {} },
                    });
                };
            },
            g = function () {
                return function (e) {
                    e({
                        type: "callCenter/FORCE_RENDER_CUSTOMER_CARE",
                        payload: { forceRenderCustomerCare: {} },
                    });
                };
            },
            v = function (e) {
                return function (t) {
                    t({
                        type: "callCenter/SET_STRINGEE_CLIENT",
                        payload: { stringeeClient: e },
                    });
                };
            },
            E = function (e) {
                return function (t) {
                    t({
                        type: "callCenter/SET_STRINGEE_AUTHEN",
                        payload: { stringeeAuthen: e },
                    });
                };
            },
            T = function (e) {
                return function (t) {
                    t({
                        type: "callCenter/SET_CHECK_CALL_FROM_CUSTOMER_CARE_SUCCESS",
                        payload: { isCallFromCustomerCareSuccess: e },
                    });
                };
            },
            C = function (e) {
                return function (t) {
                    t({
                        type: "callCenter/SET_CHECK_CALL_FROM_CUSTOMER_CARE",
                        payload: { isCallFromCustomerCare: e },
                    });
                };
            },
            b = function (e, t) {
                return function (n) {
                    n({
                        type: "callCenter/SET_CHECK_MISSED_CALL",
                        payload: {
                            stringeeSignalingState: { code: e, reason: t },
                        },
                    });
                };
            },
            A = function (e) {
                return function (t) {
                    t({
                        type: "callCenter/SET_STRINGEE_INCOMING_CALL",
                        payload: { stringeeInComingCall: e },
                    });
                };
            },
            y = function (e, t) {
                return function (n) {
                    n({
                        type: "callCenter/SET_STRINGEE_END_CALL",
                        payload: {
                            stringeeEndCall: { status: e, isAnswered: t },
                        },
                    });
                };
            },
            O = function (e, t) {
                return function (n) {
                    n({
                        type: "callCenter/SET_STRINGEE_CALL_INFO",
                        payload: { stringeeCallInfo: { status: e, data: t } },
                    });
                };
            },
            L = function () {
                var e =
                    arguments.length > 0 && void 0 !== arguments[0]
                        ? arguments[0]
                        : {};
                return function (t, n) {
                    var a = n().callCenter.patientCaredData,
                        r = void 0 === a ? {} : a;
                    t({
                        type: "callCenter/SET_PATIENT_CARE_DATA",
                        payload: {
                            patientCaredData: Object(i.a)(
                                Object(i.a)({}, r),
                                e,
                            ),
                        },
                    });
                };
            },
            N = function (e, t) {
                return function (n) {
                    n({
                        type: "callCenter/GET_PATIENT_INFO_BY_PHONE",
                        payload: { patientInfo: e },
                    }),
                        t && t(e);
                };
            },
            S = function (e) {
                var t = e.key,
                    n = e.value;
                return function (e, a) {
                    var o = a().callCenter.callingInfo,
                        c = void 0 === o ? {} : o;
                    e({
                        type: "callCenter/SET_CALLING_INFO",
                        payload: {
                            callingInfo: Object(i.a)(
                                Object(i.a)({}, c),
                                {},
                                Object(r.a)({}, t, n),
                            ),
                        },
                    });
                };
            },
            M = function (e) {
                return function (t) {
                    t({
                        type: "callCenter/SET_IS_NEW_CALL",
                        payload: { isNewCall: e },
                    });
                };
            },
            P = function (e, t) {
                return (function () {
                    var n = Object(a.a)(
                        d().mark(function n(a) {
                            var r, i;
                            return d().wrap(
                                function (n) {
                                    for (;;)
                                        switch ((n.prev = n.next)) {
                                            case 0:
                                                return (
                                                    (n.prev = 0),
                                                    (n.next = 3),
                                                    Object(o.l)({ phone: e })
                                                );
                                            case 3:
                                                (null === (r = n.sent) ||
                                                void 0 === r
                                                    ? void 0
                                                    : r.status) ===
                                                    c.l
                                                        .STATUS_SUCCESSFUL_RESPONSE
                                                        .SUCCEEDED &&
                                                    (a({
                                                        type: "callCenter/GET_PATIENT_INFO_BY_PHONE",
                                                        payload: {
                                                            patientInfo:
                                                                (null === r ||
                                                                void 0 === r
                                                                    ? void 0
                                                                    : r.data) || {
                                                                    phone: e,
                                                                },
                                                        },
                                                    }),
                                                    t &&
                                                        t(
                                                            null === r ||
                                                                void 0 === r
                                                                ? void 0
                                                                : r.data,
                                                        )),
                                                    (n.next = 10);
                                                break;
                                            case 7:
                                                (n.prev = 7),
                                                    (n.t0 = n.catch(0)),
                                                    Object(h.b)(
                                                        null === n.t0 ||
                                                            void 0 === n.t0
                                                            ? void 0
                                                            : null ===
                                                                    (i =
                                                                        n.t0
                                                                            .response) ||
                                                                void 0 === i
                                                              ? void 0
                                                              : i.status,
                                                    );
                                            case 10:
                                            case "end":
                                                return n.stop();
                                        }
                                },
                                n,
                                null,
                                [[0, 7]],
                            );
                        }),
                    );
                    return function (e) {
                        return n.apply(this, arguments);
                    };
                })();
            },
            D = function (e) {
                return (function () {
                    var t = Object(a.a)(
                        d().mark(function t(n) {
                            var a, r, i, u;
                            return d().wrap(
                                function (t) {
                                    for (;;)
                                        switch ((t.prev = t.next)) {
                                            case 0:
                                                return (
                                                    (t.prev = 0),
                                                    (t.next = 3),
                                                    Object(o.i)()
                                                );
                                            case 3:
                                                (null === (a = t.sent) ||
                                                void 0 === a
                                                    ? void 0
                                                    : a.status) ===
                                                    c.l
                                                        .STATUS_SUCCESSFUL_RESPONSE
                                                        .SUCCEEDED &&
                                                    (n({
                                                        type: "callCenter/GET_NUMBER_CALL_CENTER",
                                                        payload: {
                                                            numbersCallCenter:
                                                                null === a ||
                                                                void 0 === a
                                                                    ? void 0
                                                                    : null ===
                                                                            (r =
                                                                                a.data) ||
                                                                        void 0 ===
                                                                            r
                                                                      ? void 0
                                                                      : null ===
                                                                              (i =
                                                                                  r
                                                                                      .numbers[0]) ||
                                                                          void 0 ===
                                                                              i
                                                                        ? void 0
                                                                        : i.phone_number,
                                                        },
                                                    }),
                                                    e &&
                                                        e(
                                                            null === a ||
                                                                void 0 === a
                                                                ? void 0
                                                                : a.data,
                                                        )),
                                                    (t.next = 13);
                                                break;
                                            case 7:
                                                if (
                                                    ((t.prev = 7),
                                                    (t.t0 = t.catch(0)),
                                                    null === t.t0 ||
                                                    void 0 === t.t0
                                                        ? void 0
                                                        : t.t0.response)
                                                ) {
                                                    t.next = 12;
                                                    break;
                                                }
                                                return (
                                                    Object(s.a)(
                                                        "error",
                                                        l.a.t(
                                                            "stringee.setting.error",
                                                        ),
                                                    ),
                                                    t.abrupt("return")
                                                );
                                            case 12:
                                                Object(h.b)(
                                                    null === t.t0 ||
                                                        void 0 === t.t0
                                                        ? void 0
                                                        : null ===
                                                                (u =
                                                                    t.t0
                                                                        .response) ||
                                                            void 0 === u
                                                          ? void 0
                                                          : u.status,
                                                );
                                            case 13:
                                            case "end":
                                                return t.stop();
                                        }
                                },
                                t,
                                null,
                                [[0, 7]],
                            );
                        }),
                    );
                    return function (e) {
                        return t.apply(this, arguments);
                    };
                })();
            },
            R = function (e, t) {
                return (function () {
                    var n = Object(a.a)(
                        d().mark(function n(a, r) {
                            var l, s, m, p, f;
                            return d().wrap(
                                function (n) {
                                    for (;;)
                                        switch ((n.prev = n.next)) {
                                            case 0:
                                                return (
                                                    (l = r()),
                                                    (s =
                                                        l.callCenter
                                                            .stringeeEndCall),
                                                    (m = void 0 === s ? {} : s),
                                                    (n.prev = 1),
                                                    (n.next = 4),
                                                    Object(o.c)(e)
                                                );
                                            case 4:
                                                ((null === (p = n.sent) ||
                                                void 0 === p
                                                    ? void 0
                                                    : p.status) !==
                                                    c.l
                                                        .STATUS_SUCCESSFUL_RESPONSE
                                                        .SUCCEEDED &&
                                                    (null === p || void 0 === p
                                                        ? void 0
                                                        : p.status) !==
                                                        c.l
                                                            .STATUS_SUCCESSFUL_RESPONSE
                                                            .CREATED) ||
                                                    (Object(u.b)(function () {
                                                        a({
                                                            type: "callCenter/CREATE_SAVE_CALL_CENTER",
                                                            payload: {
                                                                saveCallCenter:
                                                                    null ===
                                                                        p ||
                                                                    void 0 === p
                                                                        ? void 0
                                                                        : p.data,
                                                            },
                                                        }),
                                                            a({
                                                                type: "callCenter/SET_STRINGEE_END_CALL",
                                                                payload: {
                                                                    stringeeEndCall:
                                                                        Object(
                                                                            i.a,
                                                                        )(
                                                                            Object(
                                                                                i.a,
                                                                            )(
                                                                                {},
                                                                                m,
                                                                            ),
                                                                            {},
                                                                            {
                                                                                status: !1,
                                                                                isAnswered:
                                                                                    !1,
                                                                            },
                                                                        ),
                                                                },
                                                            }),
                                                            a({
                                                                type: "callCenter/SET_RERENDER_MISSED_CALL",
                                                                payload: {
                                                                    forceRenderMissedCall:
                                                                        {},
                                                                },
                                                            });
                                                    }),
                                                    t && t(p)),
                                                    (n.next = 13);
                                                break;
                                            case 8:
                                                (n.prev = 8),
                                                    (n.t0 = n.catch(1)),
                                                    Object(h.b)(
                                                        null === n.t0 ||
                                                            void 0 === n.t0
                                                            ? void 0
                                                            : null ===
                                                                    (f =
                                                                        n.t0
                                                                            .response) ||
                                                                void 0 === f
                                                              ? void 0
                                                              : f.status,
                                                    ),
                                                    a({
                                                        type: "callCenter/SET_STRINGEE_END_CALL",
                                                        payload: {
                                                            stringeeEndCall:
                                                                Object(i.a)(
                                                                    Object(i.a)(
                                                                        {},
                                                                        m,
                                                                    ),
                                                                    {},
                                                                    {
                                                                        status: !1,
                                                                        isAnswered:
                                                                            !1,
                                                                    },
                                                                ),
                                                        },
                                                    }),
                                                    t && t();
                                            case 13:
                                            case "end":
                                                return n.stop();
                                        }
                                },
                                n,
                                null,
                                [[1, 8]],
                            );
                        }),
                    );
                    return function (e, t) {
                        return n.apply(this, arguments);
                    };
                })();
            },
            _ = function () {
                return (function () {
                    var e = Object(a.a)(
                        d().mark(function e(t) {
                            var n, a;
                            return d().wrap(
                                function (e) {
                                    for (;;)
                                        switch ((e.prev = e.next)) {
                                            case 0:
                                                return (
                                                    (e.prev = 0),
                                                    (e.next = 3),
                                                    Object(o.a)()
                                                );
                                            case 3:
                                                (null === (n = e.sent) ||
                                                void 0 === n
                                                    ? void 0
                                                    : n.status) ===
                                                    c.l
                                                        .STATUS_SUCCESSFUL_RESPONSE
                                                        .SUCCEEDED &&
                                                    t({
                                                        type: "callCenter/CHECK_AGENT",
                                                        payload: {
                                                            isAgent:
                                                                null === n ||
                                                                void 0 === n
                                                                    ? void 0
                                                                    : n.data,
                                                        },
                                                    }),
                                                    (e.next = 10);
                                                break;
                                            case 7:
                                                (e.prev = 7),
                                                    (e.t0 = e.catch(0)),
                                                    Object(h.b)(
                                                        null === e.t0 ||
                                                            void 0 === e.t0
                                                            ? void 0
                                                            : null ===
                                                                    (a =
                                                                        e.t0
                                                                            .response) ||
                                                                void 0 === a
                                                              ? void 0
                                                              : a.status,
                                                    );
                                            case 10:
                                            case "end":
                                                return e.stop();
                                        }
                                },
                                e,
                                null,
                                [[0, 7]],
                            );
                        }),
                    );
                    return function (t) {
                        return e.apply(this, arguments);
                    };
                })();
            },
            w = function () {
                var e =
                    arguments.length > 0 && void 0 !== arguments[0]
                        ? arguments[0]
                        : null;
                return (function () {
                    var t = Object(a.a)(
                        d().mark(function t(n) {
                            var a, r, i, l;
                            return d().wrap(
                                function (t) {
                                    for (;;)
                                        switch ((t.prev = t.next)) {
                                            case 0:
                                                return (
                                                    (t.prev = 0),
                                                    (t.next = 3),
                                                    Object(o.h)()
                                                );
                                            case 3:
                                                (null === (a = t.sent) ||
                                                void 0 === a
                                                    ? void 0
                                                    : a.status) ===
                                                    c.l
                                                        .STATUS_SUCCESSFUL_RESPONSE
                                                        .SUCCEEDED &&
                                                    (n({
                                                        type: "callCenter/GET_COUNT_MISSED_CALL",
                                                        payload: {
                                                            countMissedCall: {
                                                                countMissedCall:
                                                                    (null ===
                                                                        a ||
                                                                    void 0 === a
                                                                        ? void 0
                                                                        : null ===
                                                                                (r =
                                                                                    a.data) ||
                                                                            void 0 ===
                                                                                r
                                                                          ? void 0
                                                                          : r.countMissedCall) ||
                                                                    0,
                                                                maxMissedCall:
                                                                    (null ===
                                                                        a ||
                                                                    void 0 === a
                                                                        ? void 0
                                                                        : null ===
                                                                                (i =
                                                                                    a.data) ||
                                                                            void 0 ===
                                                                                i
                                                                          ? void 0
                                                                          : i.maxMissedCall) ||
                                                                    0,
                                                            },
                                                        },
                                                    }),
                                                    e && e()),
                                                    (t.next = 10);
                                                break;
                                            case 7:
                                                (t.prev = 7),
                                                    (t.t0 = t.catch(0)),
                                                    Object(h.b)(
                                                        null === t.t0 ||
                                                            void 0 === t.t0
                                                            ? void 0
                                                            : null ===
                                                                    (l =
                                                                        t.t0
                                                                            .response) ||
                                                                void 0 === l
                                                              ? void 0
                                                              : l.status,
                                                    );
                                            case 10:
                                            case "end":
                                                return t.stop();
                                        }
                                },
                                t,
                                null,
                                [[0, 7]],
                            );
                        }),
                    );
                    return function (e) {
                        return t.apply(this, arguments);
                    };
                })();
            },
            I = function (e) {
                return function (t) {
                    t({
                        type: "callCenter/CARE_SOFT_CALL_ID",
                        payload: { careSoftCallId: e },
                    });
                };
            };
        t.c = function () {
            var e =
                    arguments.length > 0 && void 0 !== arguments[0]
                        ? arguments[0]
                        : m,
                t = arguments.length > 1 ? arguments[1] : void 0,
                n = t.type,
                a = t.payload;
            switch (n) {
                case "callCenter/SET_STRINGEE_VISIBLE":
                case "callCenter/SET_STRINGEE_CLIENT":
                case "callCenter/SET_STRINGEE_AUTHEN":
                case "callCenter/SET_STRINGEE_SIGNALING_STATE":
                case "callCenter/SET_STRINGEE_INCOMING_CALL":
                case "callCenter/SET_STRINGEE_END_CALL":
                case "callCenter/SET_STRINGEE_CALL_INFO":
                case "callCenter/GET_PATIENT_INFO_BY_PHONE":
                case "callCenter/GET_NUMBER_CALL_CENTER":
                case "callCenter/CREATE_SAVE_CALL_CENTER":
                case "callCenter/SET_CHECK_MISSED_CALL":
                case "callCenter/SET_RERENDER_MISSED_CALL":
                case "callCenter/UPDATE_MISSED_CALL_STATUS":
                case "callCenter/SET_CHECK_CALL_FROM_CUSTOMER_CARE_SUCCESS":
                case "callCenter/SET_CHECK_CALL_FROM_CUSTOMER_CARE":
                case "callCenter/SET_PATIENT_CARE_DATA":
                case "callCenter/SET_CALLING_INFO":
                case "callCenter/FORCE_RENDER_CUSTOMER_CARE":
                case "callCenter/CHECK_AGENT":
                case "callCenter/GET_COUNT_MISSED_CALL":
                case "callCenter/SET_IS_NEW_CALL":
                case "callCenter/CARE_SOFT_CALL_ID":
                    return Object(i.a)(Object(i.a)({}, e), a);
                default:
                    return Object(i.a)({}, e);
            }
        };
    },
});
