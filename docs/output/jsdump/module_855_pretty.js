({
    855: function (e, t, n) {
        "use strict";
        var a,
            r = n(2),
            i = n(8),
            o = n(15),
            c = n(158),
            l = n(137),
            u = n(237),
            s = n(635),
            h = n(230),
            d = n(392),
            m = n(197),
            p = n(132),
            f = n(52),
            g = n(93),
            v = n(65),
            E = n(119),
            T = n(1),
            C = n(3),
            b = n(7),
            A = n.n(b),
            y = n(88),
            O = n(0),
            L = n.n(O),
            N = n(23),
            S = n(9),
            M = n(147),
            P = n(14),
            D = n(94),
            R = n(450),
            _ = n(124),
            w = n(41),
            I = n(126),
            j = n(32),
            x = n(73),
            k = {
                remindAppointment: "APPOINTMENT",
                remindMedicationSchedule: "PRESCRIPTION",
                takeCareAfterExam: "TREATMENT",
                remindBirthday: "BIRTHDAY",
                syntheticCustomer: "OTHER",
                customerCareTotal: "COMMON",
            },
            H = P.d.div(
                a ||
                    (a = Object(o.a)([
                        "\n  .label-text {\n    min-width: 200px;\n    font-size: 14px;\n    color: ",
                        ";\n  }\n  .label-text::after {\n    content: ' *';\n    color: #ff0000;\n  }\n  .normal-text {\n    n-width: 200px;\n    font-size: 14px;\n    color: ",
                        ";\n  }\n",
                    ])),
                function (e) {
                    return e.theme.textColor;
                },
                function (e) {
                    return e.theme.textColor;
                },
            ),
            F = [
                { id: "SMS", value: C.a.t("manualSms") },
                { id: "CALL", value: C.a.t("manualCall") },
                { id: "CHAT", value: C.a.t("manualChat") },
                { id: "GIFT", value: C.a.t("manualGift") },
            ],
            V = [
                { id: "TO_DO", value: C.a.t("notYetTakeCare") },
                { id: "DONE", value: C.a.t("takeCareSuccess") },
                { id: "CONNECT_LATER", value: C.a.t("needCaretAgain") },
            ],
            B = h.c.filter(function (e) {
                return "COMMON" !== e.id && "BIRTHDAY" !== e.id;
            }),
            X = function (e) {
                return "DONE" === e || "CONNECT_LATER" === e;
            },
            Z = function (e) {
                var t = e.value,
                    n = e.onChange,
                    a = e.disableToDoStatus,
                    r = Object(N.c)().t;
                return L.a.createElement(
                    c.a,
                    null,
                    L.a.createElement(
                        l.a,
                        { span: 8 },
                        L.a.createElement(
                            "div",
                            { className: "label-text" },
                            r("careStatus"),
                        ),
                    ),
                    L.a.createElement(
                        l.a,
                        { span: 16 },
                        L.a.createElement(
                            u.a.Group,
                            {
                                value: t,
                                onChange: function (e) {
                                    n(e.target.value);
                                },
                            },
                            V.map(function (e) {
                                return L.a.createElement(
                                    u.a,
                                    {
                                        key: e.id,
                                        value: e.id,
                                        className: "normal-text",
                                        disabled: a && "TO_DO" === e.id,
                                    },
                                    e.value,
                                );
                            }),
                        ),
                    ),
                );
            },
            G = function () {
                var e =
                    arguments.length > 0 && void 0 !== arguments[0]
                        ? arguments[0]
                        : 0;
                switch (arguments.length > 1 ? arguments[1] : void 0) {
                    case R.b.MONTH:
                        return 30 * Number(e) * 24;
                    case R.b.WEEK:
                        return 7 * Number(e) * 24;
                    default:
                        return 24 * Number(e);
                }
            };
        t.a = function (e) {
            var t,
                n,
                a,
                o,
                u,
                C,
                b = e.show,
                P = e.hide,
                V = e.data,
                U = e.setSentId,
                Y = e.patientId,
                z = e.patientIds,
                K = e.dataCol,
                Q = e.forceUpdate,
                W = e.forceUpdateMainTable,
                q = e.isCallFromCustomerCareSuccess,
                J = void 0 !== q && q,
                $ = e.type,
                ee = e.isCreateFromSyntheticCustomer,
                te = void 0 !== ee && ee,
                ne = e.showPatientInfo,
                ae = void 0 !== ne && ne,
                re = e.isCreateMessage,
                ie = void 0 !== re && re,
                oe = Object(S.e)(function (e) {
                    return { account: e.account.account };
                }).account,
                ce = !V,
                le = Object(N.c)().t,
                ue = Object(y.d)(),
                se = Object(i.a)(ue, 1)[0],
                he = Object(S.d)(),
                de = Object(S.e)(function (e) {
                    return e.account.allUsersIncludeDeactived;
                }),
                me = Object(O.useState)(A()()),
                pe = Object(i.a)(me, 2),
                fe = pe[0],
                ge = pe[1],
                ve = Object(O.useState)(!1),
                Ee = Object(i.a)(ve, 2),
                Te = Ee[0],
                Ce = Ee[1],
                be = Object(h.f)(),
                Ae = Object(h.g)();
            Object(O.useEffect)(function () {
                !de.length && he(Object(M.j)()), !ce && ye();
            }, []);
            var ye = function () {
                    var e = V || {},
                        t = e.sentDate,
                        n = e.sentNotification,
                        a = e.sentStatus,
                        r = e.note,
                        i = e.result,
                        o = e.status;
                    se.setFieldsValue({
                        sentDate: A()(t),
                        time: A()(t),
                        notiCategory:
                            null === n || void 0 === n ? void 0 : n.category,
                        notiType:
                            a &&
                            "NONE" !==
                                (null === a || void 0 === a ? void 0 : a[0])
                                ? null === a || void 0 === a
                                    ? void 0
                                    : a[0]
                                : void 0,
                        staff: null === n || void 0 === n ? void 0 : n.staff,
                        result: r || i,
                        status: o,
                    });
                },
                Oe = Object(O.useState)(
                    X(null === V || void 0 === V ? void 0 : V.status),
                ),
                Le = Object(i.a)(Oe, 2),
                Ne = Le[0],
                Se = Le[1];
            return L.a.createElement(
                g.a,
                {
                    visible: b,
                    width: 750,
                    title: le(
                        ce ? "scheduleCareAppointment" : "updateCareSchedule",
                    ),
                    onClose: P,
                },
                L.a.createElement(
                    H,
                    null,
                    L.a.createElement(
                        p.a,
                        {
                            form: se,
                            onSubmit: function (e) {
                                var t,
                                    n,
                                    a = Object(r.a)(
                                        Object(r.a)(
                                            {},
                                            Object(I.omit)(e, [
                                                "periodicCareDay",
                                                "endTimeSchedule",
                                            ]),
                                        ),
                                        {},
                                        {
                                            status:
                                                null !== (t = e.status) &&
                                                void 0 !== t
                                                    ? t
                                                    : "TO_DO",
                                            notiType:
                                                null !== (n = e.notiType) &&
                                                void 0 !== n
                                                    ? n
                                                    : "NONE",
                                            patientId: Y,
                                            patientIds: z,
                                            refId:
                                                null === K || void 0 === K
                                                    ? void 0
                                                    : K.refId,
                                            creator:
                                                null === oe || void 0 === oe
                                                    ? void 0
                                                    : oe.id,
                                            messageId: Ae,
                                            type: $
                                                ? (null === K || void 0 === K
                                                      ? void 0
                                                      : K.type) ||
                                                  (null === e || void 0 === e
                                                      ? void 0
                                                      : e.type)
                                                : void 0,
                                            content: te
                                                ? null === K || void 0 === K
                                                    ? void 0
                                                    : K.content
                                                : void 0,
                                        },
                                    );
                                (a.time = A()(e.time).format(
                                    T.l.DATE_FORMAT.HH_MM_SS,
                                )),
                                    (a.sentDate = A()(
                                        ""
                                            .concat(
                                                A()(e.sentDate).format(
                                                    T.l.DATE_FORMAT.YYYY_MM_DD,
                                                ),
                                            )
                                            .concat(a.time),
                                        T.l.DATE_FORMAT.YYYY_MM_DD_HH_MM_SS,
                                    ).toISOString()),
                                    (null === e || void 0 === e
                                        ? void 0
                                        : e.periodicCareDay) &&
                                        ((a.recurringFrequencyHour = G(
                                            e.periodicCareDay.time,
                                            e.periodicCareDay.unit,
                                        )),
                                        (a.recurringEndDate = A()(
                                            e.endTimeSchedule,
                                        ).toISOString()));
                                try {
                                    var i, o;
                                    (
                                        null === V || void 0 === V
                                            ? void 0
                                            : null ===
                                                    (i = V.sentNotification) ||
                                                void 0 === i
                                              ? void 0
                                              : i.id
                                    )
                                        ? Object(_.u)(
                                              Object(r.a)(
                                                  Object(r.a)({}, a),
                                                  {},
                                                  {
                                                      id:
                                                          null === V ||
                                                          void 0 === V
                                                              ? void 0
                                                              : null ===
                                                                      (o =
                                                                          V.sentNotification) ||
                                                                  void 0 === o
                                                                ? void 0
                                                                : o.id,
                                                  },
                                              ),
                                          ).then(function () {
                                              P(),
                                                  ie
                                                      ? Object(j.a)(
                                                            "success",
                                                            le(
                                                                "addSuccessfully",
                                                            ),
                                                            1.5,
                                                        )
                                                      : Object(j.a)(
                                                            "success",
                                                            le(
                                                                "updateSuccessfully",
                                                            ),
                                                            1.5,
                                                        ),
                                                  Q && Q(),
                                                  W && W();
                                          })
                                        : Object(_.a)(a).then(function (e) {
                                              U && U(e.data.id),
                                                  J &&
                                                      he(
                                                          Object(w.l)(
                                                              Object(r.a)(
                                                                  Object(r.a)(
                                                                      {},
                                                                      K,
                                                                  ),
                                                                  {},
                                                                  {
                                                                      sentId: e
                                                                          .data
                                                                          .id,
                                                                  },
                                                              ),
                                                          ),
                                                      ),
                                                  P(),
                                                  Object(j.a)(
                                                      "success",
                                                      le("addSuccessfully"),
                                                      1.5,
                                                  ),
                                                  Q && Q(),
                                                  W && W();
                                          });
                                } catch (c) {
                                    throw c;
                                }
                            },
                            onCancel: P,
                            isLoading: !1,
                            onValuesChange: function (e, t) {
                                if (
                                    "sentDate" in e &&
                                    (null === t || void 0 === t
                                        ? void 0
                                        : t.sentDate)
                                ) {
                                    var n = se.getFieldValue("endTimeSchedule");
                                    !!n &&
                                        A()(n).isBefore(
                                            A()(
                                                null === t || void 0 === t
                                                    ? void 0
                                                    : t.sentDate,
                                            ),
                                        ) &&
                                        se.resetFields(["endTimeSchedule"]),
                                        ge(
                                            null === t || void 0 === t
                                                ? void 0
                                                : t.sentDate,
                                        );
                                }
                                "periodicCareDay" in e &&
                                    se.validateFields(["endTimeSchedule"]),
                                    "status" in e && Se(X(t.status));
                            },
                        },
                        ae &&
                            L.a.createElement(
                                L.a.Fragment,
                                null,
                                L.a.createElement(
                                    c.a,
                                    { gutter: 12 },
                                    L.a.createElement(
                                        l.a,
                                        { span: 12 },
                                        L.a.createElement(f.d, {
                                            component: x.a,
                                            label: le("profileNumber"),
                                            required: !0,
                                            propsComponent: {
                                                value:
                                                    null === V || void 0 === V
                                                        ? void 0
                                                        : null ===
                                                                (t =
                                                                    V.sentNotification) ||
                                                            void 0 === t
                                                          ? void 0
                                                          : null ===
                                                                  (n =
                                                                      t.patient) ||
                                                              void 0 === n
                                                            ? void 0
                                                            : n.pid,
                                                disabled: !0,
                                            },
                                        }),
                                    ),
                                    L.a.createElement(
                                        l.a,
                                        { span: 12 },
                                        L.a.createElement(f.d, {
                                            component: x.a,
                                            label: le("phoneNumberShort"),
                                            propsComponent: {
                                                value:
                                                    null === V || void 0 === V
                                                        ? void 0
                                                        : null ===
                                                                (a =
                                                                    V.sentNotification) ||
                                                            void 0 === a
                                                          ? void 0
                                                          : null ===
                                                                  (o =
                                                                      a.patient) ||
                                                              void 0 === o
                                                            ? void 0
                                                            : o.phone,
                                                disabled: !0,
                                            },
                                        }),
                                    ),
                                ),
                                L.a.createElement(
                                    c.a,
                                    { gutter: 12 },
                                    L.a.createElement(
                                        l.a,
                                        { span: 24 },
                                        L.a.createElement(f.d, {
                                            component: x.a,
                                            label: le("name"),
                                            required: !0,
                                            propsComponent: {
                                                value:
                                                    null === V || void 0 === V
                                                        ? void 0
                                                        : null ===
                                                                (u =
                                                                    V.sentNotification) ||
                                                            void 0 === u
                                                          ? void 0
                                                          : null ===
                                                                  (C =
                                                                      u.patient) ||
                                                              void 0 === C
                                                            ? void 0
                                                            : C.name,
                                                disabled: !0,
                                            },
                                        }),
                                    ),
                                ),
                            ),
                        L.a.createElement(
                            c.a,
                            { gutter: 12 },
                            L.a.createElement(
                                l.a,
                                { span: 12 },
                                L.a.createElement(f.d, {
                                    component: m.a,
                                    name: "sentDate",
                                    label: le("time"),
                                    initialValue: A()(),
                                    required: !0,
                                    rules: [
                                        {
                                            required: !0,
                                            message: "".concat(
                                                le("pleaseEnterDate"),
                                            ),
                                        },
                                    ],
                                    propsComponent: {
                                        format: T.Nb,
                                        placeholder: T.cb,
                                    },
                                }),
                            ),
                            L.a.createElement(
                                l.a,
                                { span: 12 },
                                L.a.createElement(f.d, {
                                    name: "time",
                                    label: " ",
                                    component: s.a,
                                    initialValue: A()(),
                                    rules: [
                                        {
                                            required: !0,
                                            message: "".concat(
                                                le("pleaseEnterTime"),
                                            ),
                                        },
                                    ],
                                    propsComponent: {
                                        allowClear: !0,
                                        format: T.l.DATE_FORMAT.HH_MM,
                                        placeholder: le("Select time"),
                                        minuteStep: 5,
                                    },
                                }),
                            ),
                        ),
                        L.a.createElement(
                            c.a,
                            { gutter: 12 },
                            L.a.createElement(
                                l.a,
                                { span: 12 },
                                L.a.createElement(f.d, {
                                    name: "notiCategory",
                                    label: le("careType"),
                                    component: v.a,
                                    required: !0,
                                    rules: [
                                        {
                                            required: !0,
                                            message: "".concat(
                                                le("pleaseSelectCareType"),
                                            ),
                                        },
                                    ],
                                    initialValue: k.syntheticCustomer,
                                    propsComponent: {
                                        allowClear: !1,
                                        placeholder: le("careType"),
                                        data: B,
                                    },
                                }),
                            ),
                            L.a.createElement(
                                l.a,
                                { span: 12 },
                                L.a.createElement(f.d, {
                                    name: ["staff", "id"],
                                    label: le("employeesCare"),
                                    component: v.a,
                                    required: !0,
                                    rules: [
                                        {
                                            required: !0,
                                            message: "".concat(
                                                le(
                                                    "pleaseSelectAEmployeesCare",
                                                ),
                                            ),
                                        },
                                    ],
                                    propsComponent: {
                                        placeholder: le("employeesCare"),
                                        data: de,
                                        showSearch: !0,
                                        accessorLabel: "firstName",
                                        accessorValue: "id",
                                        dropdownMatchSelectWidth: !1,
                                    },
                                }),
                            ),
                        ),
                        L.a.createElement(
                            c.a,
                            { gutter: 12 },
                            L.a.createElement(
                                l.a,
                                { span: 24 },
                                L.a.createElement(f.d, {
                                    name: "result",
                                    label: le("careContent"),
                                    validateFirst: !0,
                                    required: !0,
                                    rules: [
                                        {
                                            required: !0,
                                            message: "".concat(
                                                le("pleaseEnterResultCare"),
                                            ),
                                        },
                                        { validator: D.e },
                                    ],
                                    component: E.a,
                                    propsComponent: {
                                        maxLength: 600,
                                        minRows: 3,
                                    },
                                }),
                            ),
                        ),
                        ce
                            ? L.a.createElement(
                                  c.a,
                                  null,
                                  L.a.createElement(
                                      c.a,
                                      null,
                                      L.a.createElement(
                                          d.a,
                                          {
                                              value: Te,
                                              onChange: function () {
                                                  return Ce(!Te);
                                              },
                                          },
                                          le("periodicCare"),
                                      ),
                                  ),
                                  Te &&
                                      L.a.createElement(
                                          c.a,
                                          { className: "mt-10", gutter: 20 },
                                          L.a.createElement(
                                              l.a,
                                              { span: 12 },
                                              L.a.createElement(f.d, {
                                                  label: le("time"),
                                                  component: R.c,
                                                  name: "periodicCareDay",
                                                  required: !0,
                                                  rules: [
                                                      {
                                                          required: !0,
                                                          message: "".concat(
                                                              le(
                                                                  "Please select {{fieldName}}",
                                                                  {
                                                                      fieldName:
                                                                          le(
                                                                              "time",
                                                                          ).toLocaleLowerCase(),
                                                                  },
                                                              ),
                                                          ),
                                                      },
                                                      {
                                                          validator: function (
                                                              e,
                                                              t,
                                                              n,
                                                          ) {
                                                              return Object(
                                                                  D.y,
                                                              )(e, t, n);
                                                          },
                                                      },
                                                  ],
                                              }),
                                          ),
                                          L.a.createElement(
                                              l.a,
                                              { span: 12 },
                                              L.a.createElement(f.d, {
                                                  component: m.a,
                                                  name: "endTimeSchedule",
                                                  label: le("endTimeSchedule"),
                                                  required: !0,
                                                  rules: [
                                                      {
                                                          required: !0,
                                                          message: "".concat(
                                                              le(
                                                                  "Please select {{fieldName}}",
                                                                  {
                                                                      fieldName:
                                                                          le(
                                                                              "endTimeSchedule",
                                                                          ).toLocaleLowerCase(),
                                                                  },
                                                              ),
                                                          ),
                                                      },
                                                      {
                                                          validator: function (
                                                              e,
                                                              t,
                                                              n,
                                                          ) {
                                                              return Object(
                                                                  D.o,
                                                              )(
                                                                  e,
                                                                  t,
                                                                  n,
                                                                  se.getFieldValue(
                                                                      "periodicCareDay",
                                                                  ),
                                                                  se.getFieldValue(
                                                                      "sentDate",
                                                                  ),
                                                              );
                                                          },
                                                      },
                                                  ],
                                                  propsComponent: {
                                                      format: T.Nb,
                                                      placeholder: T.cb,
                                                      disabledDate: function (
                                                          e,
                                                      ) {
                                                          return (
                                                              e &&
                                                              e <
                                                                  A()(
                                                                      fe,
                                                                  ).startOf(
                                                                      "day",
                                                                  )
                                                          );
                                                      },
                                                  },
                                              }),
                                          ),
                                      ),
                              )
                            : L.a.createElement(
                                  c.a,
                                  null,
                                  L.a.createElement(
                                      c.a,
                                      { className: "mt-20" },
                                      L.a.createElement(f.d, {
                                          name: "status",
                                          component: Z,
                                          propsComponent: {
                                              disableToDoStatus:
                                                  "CONNECT_LATER" ===
                                                  (null === V || void 0 === V
                                                      ? void 0
                                                      : V.status),
                                          },
                                          rules: [
                                              {
                                                  required: !0,
                                                  message: "".concat(
                                                      le(
                                                          "Please select {{fieldName}}",
                                                          {
                                                              fieldName:
                                                                  le(
                                                                      "careStatus",
                                                                  ).toLocaleLowerCase(),
                                                          },
                                                      ),
                                                  ),
                                              },
                                          ],
                                      }),
                                  ),
                                  L.a.createElement(
                                      c.a,
                                      { className: "mt-10" },
                                      L.a.createElement(
                                          l.a,
                                          { span: 24 },
                                          L.a.createElement(f.d, {
                                              name: "notiType",
                                              initialValue: be,
                                              label: le("careChanel"),
                                              component: v.a,
                                              required: !0,
                                              rules: [
                                                  {
                                                      required: Ne,
                                                      message: "".concat(
                                                          le(
                                                              "pleaseSelectCareChanel",
                                                          ),
                                                      ),
                                                  },
                                              ],
                                              propsComponent: {
                                                  name: "radiogroup",
                                                  disabled: !Ne,
                                                  placeholder: le("careChanel"),
                                                  data: F,
                                                  showTextDisabled: !1,
                                              },
                                          }),
                                      ),
                                  ),
                              ),
                    ),
                ),
            );
        };
    },
});
