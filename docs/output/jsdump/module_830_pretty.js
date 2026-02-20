({
    830: function (e, t, n) {
        "use strict";
        var a,
            r = n(18),
            i = n(5),
            o = n(2),
            c = n(8),
            l = n(15),
            u = n(0),
            s = n.n(u),
            h = n(194),
            d = n.n(h),
            m = n(262),
            p = n.n(m),
            f = n(160),
            g = n.n(f),
            v = n(196),
            E = n.n(v),
            T = n(327),
            C = n.n(T),
            b = n(321),
            A = n.n(b),
            y = n(94),
            O = n(16),
            L = n(1),
            N = n(144),
            S = n(7),
            M = n.n(S),
            P = n(23),
            D = n(225),
            R = n(226),
            _ = n(9),
            w = n(93),
            I = n(54),
            j = n(14),
            x = n(63),
            k = n(254),
            H = n(306),
            F = n(795),
            V = n(119),
            B = n(326),
            X = n(805),
            Z = n(315),
            G = n(173),
            U = n(97),
            Y = n(60),
            z = n(24),
            K = n(12),
            Q = n(267),
            W = n(238),
            q = n(32),
            J = n(105),
            $ = n(630),
            ee = n(761),
            te = n(1120),
            ne = n(442),
            ae = n(87),
            re = n(400),
            ie = n(277),
            oe = n(89);
        function ce() {
            ce = function () {
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
            } catch (M) {
                l = function (e, t, n) {
                    return (e[t] = n);
                };
            }
            function u(e, t, n, r) {
                var i = t && t.prototype instanceof d ? t : d,
                    o = Object.create(i.prototype),
                    c = new L(r || []);
                return a(o, "_invoke", { value: b(e, n, c) }), o;
            }
            function s(e, t, n) {
                try {
                    return { type: "normal", arg: e.call(t, n) };
                } catch (M) {
                    return { type: "throw", arg: M };
                }
            }
            e.wrap = u;
            var h = {};
            function d() {}
            function m() {}
            function p() {}
            var f = {};
            l(f, i, function () {
                return this;
            });
            var g = Object.getPrototypeOf,
                v = g && g(g(N([])));
            v && v !== t && n.call(v, i) && (f = v);
            var E = (p.prototype = d.prototype = Object.create(f));
            function T(e) {
                ["next", "throw", "return"].forEach(function (t) {
                    l(e, t, function (e) {
                        return this._invoke(t, e);
                    });
                });
            }
            function C(e, t) {
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
            function b(e, t, n) {
                var a = "suspendedStart";
                return function (r, i) {
                    if ("executing" === a)
                        throw new Error("Generator is already running");
                    if ("completed" === a) {
                        if ("throw" === r) throw i;
                        return S();
                    }
                    for (n.method = r, n.arg = i; ; ) {
                        var o = n.delegate;
                        if (o) {
                            var c = A(o, n);
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
            function A(e, t) {
                var n = t.method,
                    a = e.iterator[n];
                if (void 0 === a)
                    return (
                        (t.delegate = null),
                        ("throw" === n &&
                            e.iterator.return &&
                            ((t.method = "return"),
                            (t.arg = void 0),
                            A(e, t),
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
            function y(e) {
                var t = { tryLoc: e[0] };
                1 in e && (t.catchLoc = e[1]),
                    2 in e && ((t.finallyLoc = e[2]), (t.afterLoc = e[3])),
                    this.tryEntries.push(t);
            }
            function O(e) {
                var t = e.completion || {};
                (t.type = "normal"), delete t.arg, (e.completion = t);
            }
            function L(e) {
                (this.tryEntries = [{ tryLoc: "root" }]),
                    e.forEach(y, this),
                    this.reset(!0);
            }
            function N(e) {
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
                return { next: S };
            }
            function S() {
                return { value: void 0, done: !0 };
            }
            return (
                (m.prototype = p),
                a(E, "constructor", { value: p, configurable: !0 }),
                a(p, "constructor", { value: m, configurable: !0 }),
                (m.displayName = l(p, c, "GeneratorFunction")),
                (e.isGeneratorFunction = function (e) {
                    var t = "function" == typeof e && e.constructor;
                    return (
                        !!t &&
                        (t === m ||
                            "GeneratorFunction" === (t.displayName || t.name))
                    );
                }),
                (e.mark = function (e) {
                    return (
                        Object.setPrototypeOf
                            ? Object.setPrototypeOf(e, p)
                            : ((e.__proto__ = p), l(e, c, "GeneratorFunction")),
                        (e.prototype = Object.create(E)),
                        e
                    );
                }),
                (e.awrap = function (e) {
                    return { __await: e };
                }),
                T(C.prototype),
                l(C.prototype, o, function () {
                    return this;
                }),
                (e.AsyncIterator = C),
                (e.async = function (t, n, a, r, i) {
                    void 0 === i && (i = Promise);
                    var o = new C(u(t, n, a, r), i);
                    return e.isGeneratorFunction(n)
                        ? o
                        : o.next().then(function (e) {
                              return e.done ? e.value : o.next();
                          });
                }),
                T(E),
                l(E, c, "Generator"),
                l(E, i, function () {
                    return this;
                }),
                l(E, "toString", function () {
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
                (e.values = N),
                (L.prototype = {
                    constructor: L,
                    reset: function (e) {
                        if (
                            ((this.prev = 0),
                            (this.next = 0),
                            (this.sent = this._sent = void 0),
                            (this.done = !1),
                            (this.delegate = null),
                            (this.method = "next"),
                            (this.arg = void 0),
                            this.tryEntries.forEach(O),
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
                                    O(n),
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
                                    O(n);
                                }
                                return r;
                            }
                        }
                        throw new Error("illegal catch attempt");
                    },
                    delegateYield: function (e, t, n) {
                        return (
                            (this.delegate = {
                                iterator: N(e),
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
        var le = j.d.div(
                a ||
                    (a = Object(l.a)([
                        "\n  .button-receipt {\n    justify-content: flex-end;\n    .wrapper-button + .wrapper-button {\n      margin-left: 10px;\n    }\n  }\n  .error-max {\n    color: inherit;\n    background: #ffe7e7;\n    padding: 8px;\n    border-top-right-radius: 4px;\n    border-bottom-right-radius: 4px;\n    margin-bottom: 5px;\n    border-left: 4px solid #ff6868;\n  }\n  .has-error .ant-input-number input,\n  .has-error .ant-input-number:hover input {\n    border-color: #f5222d !important;\n  }\n  .m-none {\n    margin: 0;\n  }\n  .m-1 {\n    margin-top: 1rem;\n  }\n  .receipt-content {\n    &.has-bank-info {\n      display: grid;\n      grid-template-columns: 1fr 1fr;\n      gap: 24px;\n\n      > div {\n        width: 100%;\n      }\n\n      .qr-code-container {\n        width: 100%;\n\n        img {\n          width: 100%;\n          height: auto;\n        }\n      }\n    }\n  }\n",
                    ])),
            ),
            ue = A.a.Option,
            se = function (e) {
                var t = e.bankId,
                    n = e.accountNo,
                    a = e.accountName,
                    r = e.amount || 0,
                    i = e.content || "",
                    o = encodeURIComponent(a),
                    c = encodeURIComponent(i),
                    l = "https://img.vietqr.io/image/"
                        .concat(t, "-")
                        .concat(n, "-")
                        .concat("compact2", ".png?amount=")
                        .concat(r, "&addInfo=")
                        .concat(c, "&accountName=")
                        .concat(o);
                return s.a.createElement(
                    "div",
                    {
                        className: "qr-code-container",
                        style: { textAlign: "center" },
                    },
                    s.a.createElement("img", {
                        src: l,
                        alt: "QR Code",
                        style: {
                            width: "100%",
                            height: "auto",
                            border: "1px solid #d9d9d9",
                            borderRadius: "4px",
                            padding: "8px",
                        },
                    }),
                );
            },
            he = g.a.create({ name: "receipt" })(
                Object(N.withRouter)(function (e) {
                    var t,
                        n,
                        a,
                        l,
                        h = e.setIsSavedReceipt,
                        m = e.fetchReceiptAndReimbursement,
                        f = e.fetchTreatmentDateOwed,
                        v = e.fetchAllInvoiceTreatment,
                        T = e.handleSetReceipt,
                        b = Object(N.useParams)(),
                        S = e.patientId || b.patientId,
                        j = Object(P.c)().t,
                        he = Object(X.a)().paymentSetting,
                        de = e.form,
                        me = de.getFieldDecorator,
                        pe = de.getFieldValue,
                        fe = e.treatment,
                        ge = e.show,
                        ve = e.account,
                        Ee = e.paymentAll,
                        Te = e.showPrint,
                        Ce = Object(_.d)(),
                        be = Object(_.e)(function (e) {
                            return e.patient.patient;
                        }),
                        Ae = Object(_.e)(function (e) {
                            return e.clinic.clinicDetail;
                        }),
                        ye =
                            null ===
                                (t = Object(oe.a)()(
                                    L.W.receipt_backdate_limit,
                                )) || void 0 === t
                                ? void 0
                                : t.value,
                        Oe = O.P(ve),
                        Le = Object(u.useState)(!1),
                        Ne = Object(c.a)(Le, 2),
                        Se = Ne[0],
                        Me = Ne[1],
                        Pe = Object(u.useState)(0),
                        De = Object(c.a)(Pe, 2),
                        Re = De[0],
                        _e = De[1],
                        we = Object(u.useState)(0),
                        Ie = Object(c.a)(we, 2),
                        je = Ie[0],
                        xe = Ie[1],
                        ke = Object(u.useState)([]),
                        He = Object(c.a)(ke, 2),
                        Fe = He[0],
                        Ve = He[1],
                        Be = fe.selectedTreatmentItems || [],
                        Xe = Object(G.a)(),
                        Ze = Object(z.b)(K.a.PATIENT.PATIENT_PAY_RECEIPT, !1),
                        Ge = Object($.a)(J.b.ADVANCE_PAYMENT),
                        Ue = Object(re.a)(),
                        Ye = Object(u.useState)(),
                        ze = Object(c.a)(Ye, 2),
                        Ke = ze[0],
                        Qe = ze[1],
                        We = Object(u.useRef)(null),
                        qe = Object(ie.b)(),
                        Je = Object(u.useState)(""),
                        $e = Object(c.a)(Je, 2),
                        et = $e[0],
                        tt = $e[1],
                        nt = Object(u.useMemo)(
                            function () {
                                return null === he || void 0 === he
                                    ? void 0
                                    : he.map(function (e) {
                                          return e.name === ee.a
                                              ? Object(o.a)(
                                                    Object(o.a)({}, e),
                                                    {},
                                                    { disabled: !Ue },
                                                )
                                              : e;
                                      });
                            },
                            [he, Ue],
                        ),
                        at = Object(u.useMemo)(
                            function () {
                                var e;
                                return null === he || void 0 === he
                                    ? void 0
                                    : null ===
                                            (e = he.find(function (e) {
                                                return e.name === ee.a;
                                            })) || void 0 === e
                                      ? void 0
                                      : e.id;
                            },
                            [he],
                        ),
                        rt = Be.reduce(function (e, t) {
                            var n = t.treatmentItem ? t.treatmentItem.name : "";
                            return e.push(n), e;
                        }, []),
                        it = function () {
                            e.form.resetFields();
                        };
                    Object(u.useEffect)(function () {
                        !Object.keys(be).length && Ce(Object(x.p)(S));
                    }, []),
                        Object(u.useEffect)(
                            function () {
                                if (ge) {
                                    var t,
                                        n,
                                        a,
                                        r,
                                        i =
                                            (null === e || void 0 === e
                                                ? void 0
                                                : null ===
                                                        (t = e.paymentInfo) ||
                                                    void 0 === t
                                                  ? void 0
                                                  : t.total) -
                                                (null === e || void 0 === e
                                                    ? void 0
                                                    : null ===
                                                            (n =
                                                                e.paymentInfo) ||
                                                        void 0 === n
                                                      ? void 0
                                                      : n.paid) ||
                                            Math.abs(
                                                (null === e || void 0 === e
                                                    ? void 0
                                                    : null ===
                                                            (a =
                                                                e.paymentInfoData) ||
                                                        void 0 === a
                                                      ? void 0
                                                      : a.balance) -
                                                    (null === e || void 0 === e
                                                        ? void 0
                                                        : null ===
                                                                (r =
                                                                    e.treatment) ||
                                                            void 0 === r
                                                          ? void 0
                                                          : r.owed),
                                            ),
                                        o = i > 0 ? i : 0;
                                    _e(+o.toFixed(0)), je || xe(+o.toFixed(0));
                                }
                            },
                            [ge],
                        ),
                        Object(u.useEffect)(
                            function () {
                                ge && Ze && ht({ patientId: S });
                            },
                            [ge, Ze, S],
                        ),
                        Object(u.useEffect)(
                            function () {
                                if (ge) {
                                    var e = Ee
                                        ? ""
                                        : Boolean(rt.length) &&
                                          "- ".concat(rt.join("\n- "));
                                    tt(e || "");
                                }
                            },
                            [ge, Ee, rt],
                        );
                    var ot = function () {
                            e.hide(!1), it();
                        },
                        ct = function (t) {
                            var n = t.valuesForm,
                                a = t.isVnPayPayment,
                                r = void 0 !== a && a,
                                i = n || We.current,
                                o = i.type,
                                c = i.isClickDistributePayment,
                                l = i.values,
                                u = {
                                    collector: { id: ve.id },
                                    amount: Object(O.k)(l.amount),
                                    patientId: be.id,
                                    payerAddress: be.address,
                                    payerName: l.name,
                                    payerPhone: l.phone || l.phone2,
                                    reason: l.content,
                                    createdTime:
                                        M()(l.paymentDate).get("hour") <
                                        M()().hour(7).get("hour")
                                            ? M()(l.paymentDate)
                                                  .set({
                                                      hour: 8,
                                                      minutes: 0,
                                                      seconds: 0,
                                                  })
                                                  .unix()
                                            : M()(l.paymentDate).unix(),
                                    creator: { id: ve.id },
                                    paymentMethod: l.paymentMethod,
                                };
                            Ee ||
                                ((u.treatmentDatePayment = { id: fe.id }),
                                (u.selectedTreatmentItems =
                                    fe.selectedTreatmentItems));
                            var s = { zalo: Ge, textAmount: O.sb(Number(Re)) };
                            Object(H.c)(u, s)
                                .then(function (t) {
                                    var n = (
                                            (null === t || void 0 === t
                                                ? void 0
                                                : t.data) || {}
                                        ).amount,
                                        a = void 0 === n ? 0 : n;
                                    if (
                                        (c ||
                                            h ||
                                            Xe({
                                                path: "".concat(S, "-payment"),
                                                data: Object(U.g)({
                                                    eventType: Y.a.create,
                                                    type: [U.c.receipt],
                                                }),
                                                screenKey: Y.b.examTreatment,
                                            }),
                                        t)
                                    ) {
                                        if (Ze) return void st(a);
                                        if ("print" === o)
                                            return e.hide(!0), void Te(t.data);
                                        e.isInvoice &&
                                            Ce({
                                                type: k.a,
                                                payload: {
                                                    moneyAddNew: +a.toFixed(0),
                                                },
                                            }),
                                            e.isFirst &&
                                                Ce({
                                                    type: k.a,
                                                    payload: {
                                                        isFirst: +a.toFixed(0),
                                                    },
                                                }),
                                            Ce(
                                                Object(k.c)([
                                                    null === t || void 0 === t
                                                        ? void 0
                                                        : t.data,
                                                ]),
                                            ),
                                            T && T(l.paymentDate),
                                            !c && Me(!1),
                                            !r && !c && e.hide(!0),
                                            it(),
                                            !r && c && h(!0),
                                            !r && m && m();
                                    }
                                })
                                .catch(function () {
                                    return Me(!1);
                                });
                        },
                        lt = (function () {
                            var e = Object(i.a)(
                                ce().mark(function e(t) {
                                    var n, a, r, i, o, c;
                                    return ce().wrap(
                                        function (e) {
                                            for (;;)
                                                switch ((e.prev = e.next)) {
                                                    case 0:
                                                        return (
                                                            (n = {
                                                                amount: Number(
                                                                    t,
                                                                ),
                                                                patient: {
                                                                    id:
                                                                        null ===
                                                                            be ||
                                                                        void 0 ===
                                                                            be
                                                                            ? void 0
                                                                            : be.id,
                                                                    name:
                                                                        null ===
                                                                            be ||
                                                                        void 0 ===
                                                                            be
                                                                            ? void 0
                                                                            : be.name,
                                                                },
                                                                user: {
                                                                    id:
                                                                        null ===
                                                                            ve ||
                                                                        void 0 ===
                                                                            ve
                                                                            ? void 0
                                                                            : ve.id,
                                                                },
                                                            }),
                                                            (a = {
                                                                status: ae.c
                                                                    .CREATED,
                                                            }),
                                                            (e.prev = 2),
                                                            (e.next = 5),
                                                            Object(ne.d)(
                                                                null === ve ||
                                                                    void 0 ===
                                                                        ve
                                                                    ? void 0
                                                                    : ve.id,
                                                                a,
                                                            )
                                                        );
                                                    case 5:
                                                        if (
                                                            !(i = e.sent) ||
                                                            !Object(ae.f)(
                                                                i.data,
                                                            )
                                                        ) {
                                                            e.next = 9;
                                                            break;
                                                        }
                                                        return (
                                                            Object(q.a)(
                                                                "error",
                                                                j(
                                                                    "existingQrCodePayment",
                                                                ),
                                                            ),
                                                            e.abrupt("return")
                                                        );
                                                    case 9:
                                                        return (
                                                            Me(!0),
                                                            (e.next = 12),
                                                            Object(ne.b)(n)
                                                        );
                                                    case 12:
                                                        (o = e.sent),
                                                            (c = JSON.parse(
                                                                null === o ||
                                                                    void 0 === o
                                                                    ? void 0
                                                                    : null ===
                                                                            (r =
                                                                                o.data) ||
                                                                        void 0 ===
                                                                            r
                                                                      ? void 0
                                                                      : r.responseDetail,
                                                            )) &&
                                                            c.code !== ae.e
                                                                ? Object(q.a)(
                                                                      "error",
                                                                      ae.a[
                                                                          c.code
                                                                      ],
                                                                  )
                                                                : Qe(
                                                                      null ===
                                                                          o ||
                                                                          void 0 ===
                                                                              o
                                                                          ? void 0
                                                                          : o.data,
                                                                  ),
                                                            Me(!1),
                                                            (e.next = 22);
                                                        break;
                                                    case 18:
                                                        throw (
                                                            ((e.prev = 18),
                                                            (e.t0 = e.catch(2)),
                                                            Me(!1),
                                                            e.t0)
                                                        );
                                                    case 22:
                                                    case "end":
                                                        return e.stop();
                                                }
                                        },
                                        e,
                                        null,
                                        [[2, 18]],
                                    );
                                }),
                            );
                            return function (t) {
                                return e.apply(this, arguments);
                            };
                        })(),
                        ut = function (t) {
                            var n =
                                arguments.length > 1 &&
                                void 0 !== arguments[1] &&
                                arguments[1];
                            e.form.validateFields(function (e, a) {
                                if (e) Me(!1);
                                else {
                                    var r = {
                                        type: t,
                                        isClickDistributePayment: n,
                                        values: a,
                                    };
                                    if (a.paymentMethod === at)
                                        return qe
                                            ? (lt(a.amount),
                                              void (We.current = r))
                                            : void Object(q.a)(
                                                  "warning",
                                                  j(
                                                      "warningSettingPermissionNoticeVNPay",
                                                  ),
                                              );
                                    Me(!0), ct({ valuesForm: r });
                                }
                            });
                        },
                        st = (function () {
                            var e = Object(i.a)(
                                ce().mark(function e() {
                                    var t,
                                        n,
                                        a,
                                        i,
                                        o,
                                        c,
                                        l,
                                        u,
                                        s,
                                        h,
                                        d,
                                        p,
                                        g,
                                        E,
                                        C = arguments;
                                    return ce().wrap(
                                        function (e) {
                                            for (;;)
                                                switch ((e.prev = e.next)) {
                                                    case 0:
                                                        for (
                                                            t =
                                                                C.length > 0 &&
                                                                void 0 !== C[0]
                                                                    ? C[0]
                                                                    : 0,
                                                                n =
                                                                    pe(
                                                                        "paymentMethod",
                                                                    ),
                                                                a =
                                                                    pe(
                                                                        "paymentDate",
                                                                    ),
                                                                i = Object(W.l)(
                                                                    Fe || [],
                                                                    t,
                                                                ),
                                                                o = [],
                                                                c = 0;
                                                            c <
                                                            (null === i ||
                                                            void 0 === i
                                                                ? void 0
                                                                : i.length);
                                                            c++
                                                        )
                                                            for (
                                                                l = i[c] || {},
                                                                    u =
                                                                        l.selectedTreatmentItems,
                                                                    s =
                                                                        void 0 ===
                                                                        u
                                                                            ? []
                                                                            : u,
                                                                    h = 0;
                                                                h <
                                                                (null === s ||
                                                                void 0 === s
                                                                    ? void 0
                                                                    : s.length);
                                                                h++
                                                            )
                                                                (d = s[h]),
                                                                    o.push(d);
                                                        return (
                                                            (p = o.reduce(
                                                                function (
                                                                    e,
                                                                    t,
                                                                ) {
                                                                    var a;
                                                                    return (
                                                                        (null ===
                                                                            t ||
                                                                        void 0 ===
                                                                            t
                                                                            ? void 0
                                                                            : t.paidAmount) &&
                                                                            (e =
                                                                                [].concat(
                                                                                    Object(
                                                                                        r.a,
                                                                                    )(
                                                                                        e,
                                                                                    ),
                                                                                    [
                                                                                        {
                                                                                            paymentMethod:
                                                                                                n ||
                                                                                                0 ===
                                                                                                    n
                                                                                                    ? {
                                                                                                          id: n,
                                                                                                      }
                                                                                                    : void 0,
                                                                                            amount:
                                                                                                (null ===
                                                                                                    t ||
                                                                                                void 0 ===
                                                                                                    t
                                                                                                    ? void 0
                                                                                                    : t.paidAmount) ||
                                                                                                0,
                                                                                            selectedTreatmentItem:
                                                                                                {
                                                                                                    id:
                                                                                                        null ===
                                                                                                            t ||
                                                                                                        void 0 ===
                                                                                                            t
                                                                                                            ? void 0
                                                                                                            : t.id,
                                                                                                    treatmentDate:
                                                                                                        {
                                                                                                            id:
                                                                                                                null ===
                                                                                                                    t ||
                                                                                                                void 0 ===
                                                                                                                    t
                                                                                                                    ? void 0
                                                                                                                    : null ===
                                                                                                                            (a =
                                                                                                                                t.treatmentDate) ||
                                                                                                                        void 0 ===
                                                                                                                            a
                                                                                                                      ? void 0
                                                                                                                      : a.id,
                                                                                                        },
                                                                                                },
                                                                                        },
                                                                                    ],
                                                                                )),
                                                                        e
                                                                    );
                                                                },
                                                                [],
                                                            )),
                                                            (g = {
                                                                createdTime:
                                                                    M()(a).get(
                                                                        "hour",
                                                                    ) <
                                                                    M()()
                                                                        .hour(7)
                                                                        .get(
                                                                            "hour",
                                                                        )
                                                                        ? M()(a)
                                                                              .set(
                                                                                  {
                                                                                      hour: 8,
                                                                                      minutes: 0,
                                                                                      seconds: 0,
                                                                                  },
                                                                              )
                                                                              .unix()
                                                                        : M()(
                                                                              a,
                                                                          ).unix(),
                                                                creator: {
                                                                    id:
                                                                        null ===
                                                                            ve ||
                                                                        void 0 ===
                                                                            ve
                                                                            ? void 0
                                                                            : ve.id,
                                                                },
                                                                invoiceTreatmentDetails:
                                                                    p,
                                                                patient: {
                                                                    id: S,
                                                                },
                                                                paymentType: 1,
                                                            }),
                                                            (e.prev = 8),
                                                            (e.next = 11),
                                                            Object(Q.b)(g)
                                                        );
                                                    case 11:
                                                        return (
                                                            (E = e.sent),
                                                            Xe({
                                                                path: "".concat(
                                                                    S,
                                                                    "-payment",
                                                                ),
                                                                data: Object(
                                                                    U.g,
                                                                )({
                                                                    eventType:
                                                                        Y.a
                                                                            .create,
                                                                    type: [
                                                                        U.c
                                                                            .receipt,
                                                                        U.c
                                                                            .invoice,
                                                                    ],
                                                                }),
                                                                screenKey:
                                                                    Y.b.payment,
                                                            }),
                                                            m && m(),
                                                            f && f(),
                                                            v && v(),
                                                            ot(),
                                                            e.abrupt(
                                                                "return",
                                                                E,
                                                            )
                                                        );
                                                    case 20:
                                                        (e.prev = 20),
                                                            (e.t0 = e.catch(8)),
                                                            Object(q.a)(
                                                                "error",
                                                                j("maxBalance"),
                                                            );
                                                    case 23:
                                                        return (
                                                            (e.prev = 23),
                                                            Ce(Object(k.c)([])),
                                                            T && T(null),
                                                            e.finish(23)
                                                        );
                                                    case 27:
                                                    case "end":
                                                        return e.stop();
                                                }
                                        },
                                        e,
                                        null,
                                        [[8, 20, 23, 27]],
                                    );
                                }),
                            );
                            return function () {
                                return e.apply(this, arguments);
                            };
                        })(),
                        ht = (function () {
                            var t = Object(i.a)(
                                ce().mark(function t() {
                                    var n,
                                        a,
                                        r,
                                        i,
                                        o,
                                        c = arguments;
                                    return ce().wrap(
                                        function (t) {
                                            for (;;)
                                                switch ((t.prev = t.next)) {
                                                    case 0:
                                                        return (
                                                            (n =
                                                                c.length > 0 &&
                                                                void 0 !== c[0]
                                                                    ? c[0]
                                                                    : {}),
                                                            (t.prev = 1),
                                                            (t.next = 4),
                                                            Object(Q.j)(n)
                                                        );
                                                    case 4:
                                                        (r = t.sent),
                                                            (i = (r || {})
                                                                .data),
                                                            (o =
                                                                void 0 === i
                                                                    ? []
                                                                    : i),
                                                            Ve(
                                                                Object(W.l)(
                                                                    o,
                                                                    (null ===
                                                                        e ||
                                                                    void 0 === e
                                                                        ? void 0
                                                                        : null ===
                                                                                (a =
                                                                                    e.paymentInfo) ||
                                                                            void 0 ===
                                                                                a
                                                                          ? void 0
                                                                          : a.balance) ||
                                                                        0,
                                                                ),
                                                            ),
                                                            (t.next = 12);
                                                        break;
                                                    case 9:
                                                        throw (
                                                            ((t.prev = 9),
                                                            (t.t0 = t.catch(1)),
                                                            t.t0)
                                                        );
                                                    case 12:
                                                    case "end":
                                                        return t.stop();
                                                }
                                        },
                                        t,
                                        null,
                                        [[1, 9]],
                                    );
                                }),
                            );
                            return function () {
                                return t.apply(this, arguments);
                            };
                        })(),
                        dt =
                            (null === Ae || void 0 === Ae
                                ? void 0
                                : null === (n = Ae.additionalInfo) ||
                                    void 0 === n
                                  ? void 0
                                  : n.bankId) &&
                            (null === Ae || void 0 === Ae
                                ? void 0
                                : null === (a = Ae.additionalInfo) ||
                                    void 0 === a
                                  ? void 0
                                  : a.bankAccount) &&
                            (null === Ae || void 0 === Ae
                                ? void 0
                                : null === (l = Ae.additionalInfo) ||
                                    void 0 === l
                                  ? void 0
                                  : l.accountHolderName);
                    return s.a.createElement(
                        w.a,
                        {
                            className: "popup-receipt",
                            width: 800,
                            visible: ge,
                            title: j(
                                Ze
                                    ? "payment.history.create_title"
                                    : "receipts",
                            ),
                            onClose: ot,
                            textToolTip: Ze ? "" : j("describeReceipts"),
                        },
                        s.a.createElement(
                            le,
                            null,
                            s.a.createElement(
                                g.a,
                                null,
                                e.isFirst &&
                                    s.a.createElement(
                                        p.a,
                                        null,
                                        s.a.createElement(
                                            d.a,
                                            { className: "error-max" },
                                            j("maxBalanceMoney", {
                                                money: Object(Z.a)(je),
                                                acronymCoin: j("acronymCoin"),
                                            }),
                                        ),
                                    ),
                                s.a.createElement(
                                    "div",
                                    {
                                        className: "receipt-content ".concat(
                                            dt ? "has-bank-info" : "",
                                        ),
                                    },
                                    s.a.createElement(
                                        "div",
                                        null,
                                        s.a.createElement(
                                            p.a,
                                            { gutter: 24 },
                                            s.a.createElement(
                                                d.a,
                                                { span: dt ? 24 : 12 },
                                                s.a.createElement(
                                                    g.a.Item,
                                                    null,
                                                    s.a.createElement(
                                                        "span",
                                                        {
                                                            className:
                                                                "required",
                                                        },
                                                        j("dateReceiptNoDate"),
                                                        " ",
                                                    ),
                                                    me("paymentDate", {
                                                        initialValue: M()(),
                                                        rules: [
                                                            {
                                                                required: !0,
                                                                message: j(
                                                                    "Please enter {{fieldName}}",
                                                                    {
                                                                        fieldName:
                                                                            j(
                                                                                "day",
                                                                            ),
                                                                    },
                                                                ),
                                                            },
                                                        ],
                                                    })(
                                                        s.a.createElement(C.a, {
                                                            format: L.l
                                                                .DATE_FORMAT
                                                                .DD_MM_YYYY_HH_MM,
                                                            placeholder: L.cb,
                                                            disabledDate:
                                                                !Oe &&
                                                                (null === ye ||
                                                                void 0 === ye
                                                                    ? void 0
                                                                    : ye.length) >
                                                                    0
                                                                    ? function (
                                                                          e,
                                                                      ) {
                                                                          var t =
                                                                                  (null ===
                                                                                      ye ||
                                                                                  void 0 ===
                                                                                      ye
                                                                                      ? void 0
                                                                                      : ye.length) >
                                                                                  0
                                                                                      ? Number(
                                                                                            ye,
                                                                                        ) /
                                                                                        24
                                                                                      : 0,
                                                                              n =
                                                                                  M()()
                                                                                      .subtract(
                                                                                          t,
                                                                                          "days",
                                                                                      )
                                                                                      .startOf(
                                                                                          "day",
                                                                                      );
                                                                          return e.isBefore(
                                                                              M()(
                                                                                  n,
                                                                              ),
                                                                              "day",
                                                                          );
                                                                      }
                                                                    : void 0,
                                                        }),
                                                    ),
                                                ),
                                            ),
                                            s.a.createElement(
                                                d.a,
                                                { span: dt ? 24 : 12 },
                                                s.a.createElement(
                                                    g.a.Item,
                                                    {
                                                        className:
                                                            "receipt-require",
                                                    },
                                                    s.a.createElement(
                                                        "span",
                                                        {
                                                            className:
                                                                "required",
                                                        },
                                                        j("payer"),
                                                        " ",
                                                    ),
                                                    me("name", {
                                                        initialValue: be.name,
                                                        rules: [
                                                            {
                                                                max: 50,
                                                                message:
                                                                    "".concat(
                                                                        j(
                                                                            "maximumLengthMessage",
                                                                            {
                                                                                maxLength: 50,
                                                                            },
                                                                        ),
                                                                    ),
                                                            },
                                                            {
                                                                required: !0,
                                                                message:
                                                                    "".concat(
                                                                        j(
                                                                            "pleaseEnterPayerName",
                                                                        ),
                                                                    ),
                                                            },
                                                        ],
                                                    })(
                                                        s.a.createElement(E.a, {
                                                            autoComplete: "off",
                                                            placeholder:
                                                                j("payer"),
                                                        }),
                                                    ),
                                                ),
                                            ),
                                        ),
                                        s.a.createElement(
                                            p.a,
                                            { gutter: 24 },
                                            s.a.createElement(
                                                d.a,
                                                { span: dt ? 24 : 12 },
                                                s.a.createElement(
                                                    g.a.Item,
                                                    {
                                                        className:
                                                            "receipt-require",
                                                    },
                                                    s.a.createElement(
                                                        "span",
                                                        null,
                                                        j("phoneNumber"),
                                                        " ",
                                                    ),
                                                    me("phone", {
                                                        initialValue:
                                                            (null === be ||
                                                            void 0 === be
                                                                ? void 0
                                                                : be.phone) ||
                                                            (null === be ||
                                                            void 0 === be
                                                                ? void 0
                                                                : be.phone2),
                                                        rules: [
                                                            { validator: y.z },
                                                        ],
                                                    })(
                                                        s.a.createElement(E.a, {
                                                            autoComplete: "off",
                                                            placeholder:
                                                                j(
                                                                    "phoneNumber",
                                                                ),
                                                        }),
                                                    ),
                                                ),
                                            ),
                                            s.a.createElement(
                                                d.a,
                                                { span: dt ? 24 : 12 },
                                                s.a.createElement(
                                                    g.a.Item,
                                                    {
                                                        className:
                                                            "receipt-require",
                                                    },
                                                    s.a.createElement(
                                                        "span",
                                                        {
                                                            className:
                                                                "required",
                                                        },
                                                        j("paymentMethod"),
                                                    ),
                                                    me("paymentMethod", {
                                                        initialValue: 1,
                                                    })(
                                                        s.a.createElement(
                                                            A.a,
                                                            {
                                                                col: 8,
                                                                placeholder:
                                                                    j(
                                                                        "paymentMethod",
                                                                    ),
                                                                showSearch: !0,
                                                                filterOption:
                                                                    function (
                                                                        e,
                                                                        t,
                                                                    ) {
                                                                        var n;
                                                                        return Object(
                                                                            B.a,
                                                                        )(
                                                                            e,
                                                                            null ===
                                                                                t ||
                                                                                void 0 ===
                                                                                    t
                                                                                ? void 0
                                                                                : null ===
                                                                                        (n =
                                                                                            t.props) ||
                                                                                    void 0 ===
                                                                                        n
                                                                                  ? void 0
                                                                                  : n.children,
                                                                        );
                                                                    },
                                                            },
                                                            nt.map(
                                                                function (e) {
                                                                    return s.a.createElement(
                                                                        ue,
                                                                        {
                                                                            key: e.id,
                                                                            value: e.id,
                                                                            disabled:
                                                                                null ===
                                                                                    e ||
                                                                                void 0 ===
                                                                                    e
                                                                                    ? void 0
                                                                                    : e.disabled,
                                                                        },
                                                                        e.name,
                                                                    );
                                                                },
                                                            ),
                                                        ),
                                                    ),
                                                ),
                                            ),
                                        ),
                                        s.a.createElement(
                                            p.a,
                                            { gutter: 24 },
                                            s.a.createElement(
                                                d.a,
                                                { span: dt ? 24 : 12 },
                                                s.a.createElement(
                                                    g.a.Item,
                                                    {
                                                        className:
                                                            "receipt-require",
                                                    },
                                                    s.a.createElement(
                                                        "span",
                                                        {
                                                            className:
                                                                "required",
                                                        },
                                                        j("amount"),
                                                    ),
                                                    me("amount", {
                                                        initialValue: Re,
                                                        onChange: function (e) {
                                                            _e(e);
                                                        },
                                                        rules: [
                                                            {
                                                                required: !0,
                                                                message: j(
                                                                    "pleaseEnterTheAmount",
                                                                ),
                                                            },
                                                            {
                                                                validator:
                                                                    function (
                                                                        t,
                                                                        n,
                                                                        a,
                                                                    ) {
                                                                        var r;
                                                                        Ze
                                                                            ? y.c(
                                                                                  t,
                                                                                  n,
                                                                                  a,
                                                                                  !0,
                                                                                  15,
                                                                                  null ===
                                                                                      e ||
                                                                                      void 0 ===
                                                                                          e
                                                                                      ? void 0
                                                                                      : null ===
                                                                                              (r =
                                                                                                  e.paymentInfo) ||
                                                                                          void 0 ===
                                                                                              r
                                                                                        ? void 0
                                                                                        : r.owed,
                                                                              )
                                                                            : y.c(
                                                                                  t,
                                                                                  n,
                                                                                  a,
                                                                                  !0,
                                                                                  15,
                                                                              );
                                                                    },
                                                            },
                                                        ],
                                                    })(
                                                        s.a.createElement(F.a, {
                                                            isMoneyNumber: !0,
                                                            placeholder:
                                                                j("amount"),
                                                        }),
                                                    ),
                                                ),
                                            ),
                                            s.a.createElement(
                                                d.a,
                                                {
                                                    span: dt ? 24 : 12,
                                                    className: "m-1",
                                                },
                                                s.a.createElement(
                                                    "span",
                                                    null,
                                                    j("text"),
                                                ),
                                                s.a.createElement(
                                                    "p",
                                                    {
                                                        className: "m-none",
                                                        style: {
                                                            fontStyle: "italic",
                                                        },
                                                    },
                                                    O.sb(Number(Re)),
                                                ),
                                            ),
                                        ),
                                        s.a.createElement(
                                            p.a,
                                            { gutter: 24 },
                                            s.a.createElement(
                                                d.a,
                                                { span: 24 },
                                                s.a.createElement(
                                                    g.a.Item,
                                                    {
                                                        className:
                                                            "receipt-require",
                                                    },
                                                    s.a.createElement(
                                                        "span",
                                                        null,
                                                        j("note"),
                                                        " ",
                                                    ),
                                                    me("content", {
                                                        initialValue: Ee
                                                            ? ""
                                                            : Boolean(
                                                                  rt.length,
                                                              ) &&
                                                              "- ".concat(
                                                                  rt.join(
                                                                      "\n- ",
                                                                  ),
                                                              ),
                                                        rules: [
                                                            {
                                                                max: 500,
                                                                message:
                                                                    "".concat(
                                                                        j(
                                                                            "maximumLengthMessage",
                                                                            {
                                                                                maxLength: 500,
                                                                            },
                                                                        ),
                                                                    ),
                                                            },
                                                        ],
                                                    })(
                                                        s.a.createElement(V.a, {
                                                            rows: 3,
                                                            placeholder:
                                                                j("content"),
                                                            onChange: function (
                                                                e,
                                                            ) {
                                                                tt(
                                                                    e.target
                                                                        .value,
                                                                );
                                                            },
                                                        }),
                                                    ),
                                                ),
                                            ),
                                        ),
                                    ),
                                    s.a.createElement(
                                        "div",
                                        null,
                                        dt &&
                                            s.a.createElement(
                                                p.a,
                                                { gutter: 24 },
                                                s.a.createElement(
                                                    d.a,
                                                    { span: 24 },
                                                    s.a.createElement(
                                                        g.a.Item,
                                                        {
                                                            label: j(
                                                                "qrCodePayment",
                                                            ),
                                                        },
                                                        s.a.createElement(se, {
                                                            bankId: Ae
                                                                .additionalInfo
                                                                .bankId,
                                                            accountNo:
                                                                Ae
                                                                    .additionalInfo
                                                                    .bankAccount,
                                                            accountName:
                                                                Ae
                                                                    .additionalInfo
                                                                    .accountHolderName,
                                                            amount: Re,
                                                            content:
                                                                et ||
                                                                pe("content") ||
                                                                "",
                                                        }),
                                                    ),
                                                ),
                                            ),
                                        s.a.createElement(
                                            p.a,
                                            null,
                                            s.a.createElement(
                                                d.a,
                                                {
                                                    sm: { span: 24 },
                                                    md: { span: 24 },
                                                },
                                                s.a.createElement(
                                                    d.a,
                                                    {
                                                        sm: { span: 24 },
                                                        md: { span: 23 },
                                                    },
                                                    s.a.createElement(
                                                        "p",
                                                        null,
                                                        j("collector"),
                                                        ": ",
                                                        ve.firstName,
                                                        " ",
                                                        ve.lastName,
                                                    ),
                                                ),
                                            ),
                                        ),
                                    ),
                                ),
                                s.a.createElement(
                                    "div",
                                    {
                                        className:
                                            "button-receipt flex justify-content-center",
                                    },
                                    s.a.createElement(
                                        I.a,
                                        { variant: "gray", onClick: ot },
                                        s.a.createElement(R.a, {
                                            icon: D.b,
                                            className: "icon-common",
                                        }),
                                        j("cancel"),
                                    ),
                                    void 0 === h ||
                                        s.a.createElement(
                                            I.a,
                                            {
                                                type: "primary",
                                                htmlType: "submit",
                                                onClick: function (e) {
                                                    ut(e, !0);
                                                },
                                                disabled: Se,
                                            },
                                            j("saveAndPaymentDistribution"),
                                        ),
                                    s.a.createElement(
                                        I.a,
                                        {
                                            type: "primary",
                                            htmlType: "submit",
                                            onClick: function (e) {
                                                return ut(e, Ze);
                                            },
                                            disabled: Se,
                                        },
                                        s.a.createElement(R.a, {
                                            icon: D.n,
                                            className: "icon-common",
                                        }),
                                        j("save"),
                                    ),
                                    !Ee &&
                                        s.a.createElement(
                                            I.a,
                                            {
                                                type: "primary",
                                                htmlType: "submit",
                                                disabled: Se,
                                                onClick: function () {
                                                    return ut("print");
                                                },
                                            },
                                            j("saveAndPrint"),
                                        ),
                                ),
                            ),
                        ),
                        Ke &&
                            s.a.createElement(te.a, {
                                show: !!Ke,
                                hide: function () {
                                    Qe();
                                },
                                onHideReceipt: ot,
                                qrCodeInfo: Ke,
                                handleCreateQrCode: lt,
                                onReloadDataPayment: ct,
                                onHidePayment: function () {
                                    var t;
                                    (null === We || void 0 === We
                                        ? void 0
                                        : null === (t = We.current) ||
                                            void 0 === t
                                          ? void 0
                                          : t.isClickDistributePayment) &&
                                        h(!0),
                                        m && m(),
                                        e.hide(!0),
                                        it();
                                },
                            }),
                    );
                }),
            );
        t.a = Object(_.c)(
            function (e) {
                return {
                    updateMenu: e.rerenderLeftMenuReducer.updated,
                    account: e.account.account,
                };
            },
            function (e) {
                return {
                    setUpdatedMenu: function (t) {
                        return e({ type: "RELOAD_LEFT_MENU", data: t });
                    },
                };
            },
        )(he);
    },
});
