"use strict";

function _createForOfIteratorHelper(r, e) { var t = "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (!t) { if (Array.isArray(r) || (t = _unsupportedIterableToArray(r)) || e && r && "number" == typeof r.length) { t && (r = t); var _n = 0, F = function F() {}; return { s: F, n: function n() { return _n >= r.length ? { done: !0 } : { done: !1, value: r[_n++] }; }, e: function e(r) { throw r; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var o, a = !0, u = !1; return { s: function s() { t = t.call(r); }, n: function n() { var r = t.next(); return a = r.done, r; }, e: function e(r) { u = !0, o = r; }, f: function f() { try { a || null == t["return"] || t["return"](); } finally { if (u) throw o; } } }; }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
(function ($) {
  $(function () {
    MPHB.calendarHelper = {
      ROOM_STATUS_AVAILABLE: 'available',
      ROOM_STATUS_NOT_AVAILABLE: 'not-available',
      ROOM_STATUS_BOOKED: 'booked',
      ROOM_STATUS_PAST: 'past',
      ROOM_STATUS_EARLIER_MIN_ADVANCE: 'earlier-min-advance',
      ROOM_STATUS_LATER_MAX_ADVANCE: 'later-max-advance',
      ROOM_STATUS_BOOKING_BUFFER: 'booking-buffer',
      /**
       * Return object with date attributes for jQuery Datepicker
       * http://keith-wood.name/datepick.html
       * based on room types availability date data
       * @param {int} calendarMode 1 - availability calendar, 2 - check-in calendar, 3 - check-out calendar
       * @param {Date} date processing date
       * @param {boolean} isCurrentMonth
       * @param {Date} checkInDate selected check-in date required for check-out calendar
       * @param {Date} minStayDateAfterCheckIn required for check-out calendar
       * @param {Date} maxStayDateAfterCheckIn required for check-out calendar
       * @param {Date} minCheckOutDateForSelection required for check-out calendar
       * @param {Date} maxCheckOutDateForSelection required for check-out calendar
       */
      getCalendarDateAttributesFromAvailability: function getCalendarDateAttributesFromAvailability(calendarMode, date, isCurrentMonth, roomTypeCalendarData) {
        var isShowPrices = arguments.length > 4 && arguments[4] !== undefined ? arguments[4] : false;
        var checkInDate = arguments.length > 5 && arguments[5] !== undefined ? arguments[5] : null;
        var minStayDateAfterCheckIn = arguments.length > 6 && arguments[6] !== undefined ? arguments[6] : null;
        var maxStayDateAfterCheckIn = arguments.length > 7 && arguments[7] !== undefined ? arguments[7] : null;
        var minCheckOutDateForSelection = arguments.length > 8 && arguments[8] !== undefined ? arguments[8] : null;
        var maxCheckOutDateForSelection = arguments.length > 9 && arguments[9] !== undefined ? arguments[9] : null;
        var calendarDateAttributes = {
          selectable: false,
          dateClass: 'mphb-date-cell',
          title: ''
        };
        if (!isCurrentMonth) {
          calendarDateAttributes.dateClass += ' mphb-extra-date';
          return calendarDateAttributes;
        }
        var formattedDate = $.datepick.formatDate('yyyy-mm-dd', date);
        var roomTypeData = roomTypeCalendarData[formattedDate];
        if (undefined === roomTypeData || 0 === Object.keys(roomTypeData).length || !roomTypeData.hasOwnProperty('roomTypeStatus')) {
          return calendarDateAttributes;
        }
        var dateBefore = new Date(date.getTime());
        dateBefore.setDate(dateBefore.getDate() - 1);
        var formattedDateBefore = $.datepick.formatDate('yyyy-mm-dd', dateBefore),
          roomTypeDataBefore = roomTypeCalendarData[formattedDateBefore],
          isDateBeforeAvailable = undefined !== roomTypeDataBefore && roomTypeDataBefore.hasOwnProperty('roomTypeStatus') && MPHB.calendarHelper.ROOM_STATUS_AVAILABLE === roomTypeDataBefore.roomTypeStatus,
          isDateBeforePast = undefined !== roomTypeDataBefore && roomTypeDataBefore.hasOwnProperty('roomTypeStatus') && MPHB.calendarHelper.ROOM_STATUS_PAST === roomTypeDataBefore.roomTypeStatus,
          isDateBeforeNotAvailable = undefined !== roomTypeDataBefore && roomTypeDataBefore.hasOwnProperty('roomTypeStatus') && MPHB.calendarHelper.ROOM_STATUS_NOT_AVAILABLE === roomTypeDataBefore.roomTypeStatus,
          isDateBeforeCheckInNotAllowed = undefined !== roomTypeDataBefore && roomTypeDataBefore.hasOwnProperty('isCheckInNotAllowed') && roomTypeDataBefore.isCheckInNotAllowed,
          isDateBeforeStayInNotAllowed = undefined !== roomTypeDataBefore && roomTypeDataBefore.hasOwnProperty('isStayInNotAllowed') && roomTypeDataBefore.isStayInNotAllowed,
          isDateBeforeOutOfSeasons = isDateBeforeNotAvailable && !isDateBeforeStayInNotAllowed && undefined !== roomTypeDataBefore && roomTypeDataBefore.hasOwnProperty('availableRoomsCount') && 0 < roomTypeDataBefore.availableRoomsCount;
        var dateAfter = new Date(date.getTime());
        dateAfter.setDate(date.getDate() + 1);
        var formattedDateAfter = $.datepick.formatDate('yyyy-mm-dd', dateAfter),
          roomTypeDataAfter = roomTypeCalendarData[formattedDateAfter],
          isDateAfterStayInNotAllowed = undefined !== roomTypeDataAfter && roomTypeDataAfter.hasOwnProperty('isStayInNotAllowed') && roomTypeDataAfter.isStayInNotAllowed,
          isDateAfterCheckOutNotAllowed = undefined !== roomTypeDataAfter && roomTypeDataAfter.hasOwnProperty('isCheckOutNotAllowed') && roomTypeDataAfter.isCheckOutNotAllowed,
          isDateAfterNotAvailable = undefined !== roomTypeDataAfter && roomTypeDataAfter.hasOwnProperty('roomTypeStatus') && MPHB.calendarHelper.ROOM_STATUS_NOT_AVAILABLE === roomTypeDataAfter.roomTypeStatus,
          isDateAfterOutOfSeasons = isDateAfterNotAvailable && !isDateAfterStayInNotAllowed && undefined !== roomTypeDataAfter && roomTypeDataAfter.hasOwnProperty('availableRoomsCount') && 0 < roomTypeDataAfter.availableRoomsCount;
        var isDateNotAvailable = MPHB.calendarHelper.ROOM_STATUS_NOT_AVAILABLE === roomTypeData.roomTypeStatus,
          isDateFullyBooked = MPHB.calendarHelper.ROOM_STATUS_BOOKED === roomTypeData.roomTypeStatus && (!roomTypeData.hasOwnProperty('isCheckInDate') || !roomTypeData.isCheckInDate) && (!roomTypeData.hasOwnProperty('isCheckOutDate') || !roomTypeData.isCheckOutDate),
          isCheckInDate = roomTypeData.hasOwnProperty('isCheckInDate') && roomTypeData.isCheckInDate,
          isCheckOutDate = roomTypeData.hasOwnProperty('isCheckOutDate') && roomTypeData.isCheckOutDate,
          isStayInNotAllowed = roomTypeData.hasOwnProperty('isStayInNotAllowed') && roomTypeData.isStayInNotAllowed,
          isCheckInNotAllowed = roomTypeData.hasOwnProperty('isCheckInNotAllowed') && roomTypeData.isCheckInNotAllowed,
          isCheckOutNotAllowed = roomTypeData.hasOwnProperty('isCheckOutNotAllowed') && roomTypeData.isCheckOutNotAllowed,
          isEarlierThanMinAdvanceDate = roomTypeData.hasOwnProperty('isEarlierThanMinAdvanceDate') && roomTypeData.isEarlierThanMinAdvanceDate,
          isLaterThanMaxAdvanceDate = roomTypeData.hasOwnProperty('isLaterThanMaxAdvanceDate') && roomTypeData.isLaterThanMaxAdvanceDate,
          isDateOutOfSeasons = isDateNotAvailable && !isStayInNotAllowed && roomTypeData.hasOwnProperty('availableRoomsCount') && 0 < roomTypeData.availableRoomsCount;
        if (MPHB.calendarHelper.ROOM_STATUS_PAST === roomTypeData.roomTypeStatus) {
          calendarDateAttributes.dateClass += ' mphb-past-date';
          // custom attribute for later processing
          calendarDateAttributes.isPastDate = true;
        } else {
          if (MPHB.calendarHelper.ROOM_STATUS_AVAILABLE === roomTypeData.roomTypeStatus) {
            calendarDateAttributes.dateClass += ' mphb-available-date';
          } else if (MPHB.calendarHelper.ROOM_STATUS_NOT_AVAILABLE === roomTypeData.roomTypeStatus) {
            calendarDateAttributes.dateClass += ' mphb-not-available-date';
          } else if (MPHB.calendarHelper.ROOM_STATUS_BOOKED === roomTypeData.roomTypeStatus) {
            calendarDateAttributes.dateClass += ' mphb-booked-date';
          }
          if (isCheckInDate) {
            calendarDateAttributes.dateClass += ' mphb-date-check-in';
          } else if (isCheckOutDate) {
            calendarDateAttributes.dateClass += ' mphb-date-check-out';
            if (MPHB.calendarHelper.ROOM_STATUS_EARLIER_MIN_ADVANCE === roomTypeData.roomTypeStatus || MPHB.calendarHelper.ROOM_STATUS_LATER_MAX_ADVANCE === roomTypeData.roomTypeStatus) {
              calendarDateAttributes.dateClass += ' mphb-booked-date mphb-available-date';
            }
          }
        }
        if (isStayInNotAllowed) {
          calendarDateAttributes.dateClass += ' mphb-not-stay-in-date';
        }
        if (isDateOutOfSeasons && isCheckOutNotAllowed) {
          if (1 === calendarMode) {
            calendarDateAttributes.dateClass += ' mphb-out-of-season-date';
          } else if (2 === calendarMode) {
            calendarDateAttributes.dateClass += ' mphb-not-check-in-date';
          } else if (3 === calendarMode) {
            calendarDateAttributes.dateClass += ' mphb-not-check-out-date';
          }

          // custom attribute for later processing
          calendarDateAttributes.isUnavailable = true;
        } else {
          if (isDateBeforeAvailable && isDateOutOfSeasons && !isCheckOutNotAllowed || isCheckOutDate && isDateOutOfSeasons || isDateAfterOutOfSeasons && isDateAfterCheckOutNotAllowed && isCheckInNotAllowed) {
            if (1 === calendarMode) {
              calendarDateAttributes.dateClass += ' mphb-out-of-season-date--check-in';
            } else if (2 === calendarMode) {
              calendarDateAttributes.dateClass += ' mphb-not-check-in-date';
            }

            // custom attribute for later processing
            calendarDateAttributes.isUnavailableCheckIn = true;
          }
          if (isDateBeforeOutOfSeasons && isCheckOutNotAllowed) {
            if (1 === calendarMode) {
              calendarDateAttributes.dateClass += ' mphb-out-of-season-date--check-out';
            } else if (3 === calendarMode) {
              calendarDateAttributes.dateClass += ' mphb-not-check-out-date';
            }

            // custom attribute for later processing
            calendarDateAttributes.isUnavailableCheckOut = true;
          }
        }
        if (isDateNotAvailable && !isStayInNotAllowed && !isDateOutOfSeasons && (!isDateBeforeAvailable || isDateBeforeCheckInNotAllowed) || isDateFullyBooked || isDateBeforeStayInNotAllowed && isCheckInDate || isCheckOutDate && isStayInNotAllowed || isStayInNotAllowed && isCheckOutNotAllowed) {
          if (1 === calendarMode) {
            calendarDateAttributes.dateClass += ' mphb-mark-as-unavailable';
          } else if (2 === calendarMode) {
            calendarDateAttributes.dateClass += ' mphb-not-check-in-date';
          } else if (3 === calendarMode) {
            calendarDateAttributes.dateClass += ' mphb-not-check-out-date';
          }

          // custom attribute for later processing
          calendarDateAttributes.isUnavailable = true;
        } else {
          if (isStayInNotAllowed || isCheckInDate || isEarlierThanMinAdvanceDate && 1 !== calendarMode || isLaterThanMaxAdvanceDate && 1 !== calendarMode || isCheckInNotAllowed && 2 === calendarMode || isCheckInNotAllowed && isDateAfterStayInNotAllowed && isDateAfterCheckOutNotAllowed || isCheckInNotAllowed && isDateAfterNotAvailable && !isDateOutOfSeasons && !isDateAfterOutOfSeasons && !isDateAfterStayInNotAllowed || isDateNotAvailable && isDateBeforeAvailable && !isDateOutOfSeasons && !isDateBeforeCheckInNotAllowed) {
            if (1 === calendarMode) {
              calendarDateAttributes.dateClass += ' mphb-mark-as-unavailable--check-in';
            } else if (2 === calendarMode) {
              calendarDateAttributes.dateClass += ' mphb-not-check-in-date';
            }

            // custom attribute for later processing
            calendarDateAttributes.isUnavailableCheckIn = true;
          }
          if (isCheckOutDate || isCheckOutNotAllowed && 3 === calendarMode || isDateBeforeStayInNotAllowed && isCheckOutNotAllowed || isDateBeforePast && isCheckOutNotAllowed || isDateBeforeNotAvailable && isCheckOutNotAllowed && !isDateBeforeOutOfSeasons) {
            if (1 === calendarMode) {
              calendarDateAttributes.dateClass += ' mphb-mark-as-unavailable--check-out';
            } else if (3 === calendarMode) {
              calendarDateAttributes.dateClass += ' mphb-not-check-out-date';
            }

            // custom attribute for later processing
            calendarDateAttributes.isUnavailableCheckOut = true;
          }
        }

        // set title
        calendarDateAttributes.title = '';
        var rulesTitles = [];
        if (MPHB.calendarHelper.ROOM_STATUS_PAST === roomTypeData.roomTypeStatus) {
          calendarDateAttributes.title = MPHB._data.translations.past;
        } else {
          if (MPHB.calendarHelper.ROOM_STATUS_AVAILABLE === roomTypeData.roomTypeStatus) {
            calendarDateAttributes.title = MPHB._data.translations.available + ' (' + (roomTypeData.hasOwnProperty('availableRoomsCount') ? roomTypeData.availableRoomsCount : 'undefined') + ')';
          }
          if (MPHB.calendarHelper.ROOM_STATUS_NOT_AVAILABLE === roomTypeData.roomTypeStatus) {
            calendarDateAttributes.title = MPHB._data.translations.notAvailable;
          }
          if (MPHB.calendarHelper.ROOM_STATUS_BOOKED === roomTypeData.roomTypeStatus) {
            calendarDateAttributes.title = MPHB._data.translations.booked;
          }
          if (MPHB.calendarHelper.ROOM_STATUS_EARLIER_MIN_ADVANCE === roomTypeData.roomTypeStatus || MPHB.calendarHelper.ROOM_STATUS_LATER_MAX_ADVANCE === roomTypeData.roomTypeStatus) {
            calendarDateAttributes.title = MPHB._data.translations.notAvailable;
          }
          if ((MPHB.calendarHelper.ROOM_STATUS_NOT_AVAILABLE === roomTypeData.roomTypeStatus || MPHB.calendarHelper.ROOM_STATUS_BOOKED === roomTypeData.roomTypeStatus) && 3 === calendarMode && !isCheckOutNotAllowed) {
            // we can not keep title empty so for checkout calendar we mark
            // unavailable days as available because user can select such dates
            calendarDateAttributes.title = MPHB._data.translations.available;
          }
          if (isStayInNotAllowed && 3 !== calendarMode) {
            rulesTitles.push(MPHB._data.translations.notStayIn);
          }
          if (isEarlierThanMinAdvanceDate || MPHB.calendarHelper.ROOM_STATUS_EARLIER_MIN_ADVANCE === roomTypeData.roomTypeStatus) {
            rulesTitles.push(MPHB._data.translations.earlierMinAdvance);
          }
          if (isLaterThanMaxAdvanceDate || MPHB.calendarHelper.ROOM_STATUS_LATER_MAX_ADVANCE === roomTypeData.roomTypeStatus) {
            rulesTitles.push(MPHB._data.translations.laterMaxAdvance);
          }
          if (isCheckInNotAllowed && 3 !== calendarMode) {
            rulesTitles.push(MPHB._data.translations.notCheckIn);
          }
          if (isCheckOutNotAllowed && 2 !== calendarMode) {
            rulesTitles.push(MPHB._data.translations.notCheckOut);
          }
        }
        if (rulesTitles.length) {
          calendarDateAttributes.title += '\n' + MPHB._data.translations.rules + ' ' + rulesTitles.join(', ');
        }

        // set price
        if (isShowPrices && roomTypeData.hasOwnProperty('price')) {
          calendarDateAttributes.content = date.getDate() + '<span class="mphb-date-cell__price">' + roomTypeData.price + '</span>';
        }

        // set selectable class and flag for Check-In and Check-Out calendars
        if (2 === calendarMode) {
          // check-in calendar

          if (!isCurrentMonth || calendarDateAttributes.isPastDate || calendarDateAttributes.isUnavailable || calendarDateAttributes.isUnavailableCheckIn) {
            calendarDateAttributes.dateClass += ' mphb-unselectable-date';
          } else {
            calendarDateAttributes.selectable = true;
            calendarDateAttributes.dateClass += ' mphb-selectable-date';
          }
        } else if (3 === calendarMode) {
          // check-out calendar

          if (isCurrentMonth) {
            if (null !== checkInDate && MPHB.Utils.formatDateToCompare(date) === MPHB.Utils.formatDateToCompare(checkInDate)) {
              calendarDateAttributes.title += ' ' + MPHB._data.translations.checkInDate;
              calendarDateAttributes.dateClass += ' mphb-check-in-date';
            }
            if (null !== minStayDateAfterCheckIn && minStayDateAfterCheckIn.getTime() > date.getTime()) {
              calendarDateAttributes.title += '\n' + MPHB._data.translations.lessThanMinDaysStay;
              calendarDateAttributes.dateClass += ' mphb-earlier-min-date';
            }
            if (null !== maxStayDateAfterCheckIn && maxStayDateAfterCheckIn.getTime() < date.getTime()) {
              calendarDateAttributes.title += '\n' + MPHB._data.translations.moreThanMaxDaysStay;
              calendarDateAttributes.dateClass += ' mphb-later-max-date';
            }
          }
          if ((null === minCheckOutDateForSelection || minCheckOutDateForSelection.getTime() <= date.getTime()) && (null === maxCheckOutDateForSelection || maxCheckOutDateForSelection.getTime() >= date.getTime()) && !calendarDateAttributes.isUnavailableCheckOut && !calendarDateAttributes.isUnavailable) {
            calendarDateAttributes.selectable = true;
            calendarDateAttributes.dateClass += ' mphb-selectable-date';
          } else {
            calendarDateAttributes.dateClass += ' mphb-unselectable-date';
          }
        }
        return calendarDateAttributes;
      },
      /**
       * @param {Date} checkInDate
       */
      calculateMinMaxCheckOutDateForSelection: function calculateMinMaxCheckOutDateForSelection(checkInDate, roomTypeCalendarData) {
        var processingDate = MPHB.Utils.cloneDate(checkInDate),
          formattedProcessingDate = $.datepick.formatDate('yyyy-mm-dd', processingDate),
          roomTypeData = null,
          isStayInAllowedInProcessingDate = false;

        // normalise date to avoide days border fluctuations
        processingDate.setHours(12, 0, 0, 0);
        var minStayDateAfterCheckIn = null;
        var maxStayDateAfterCheckIn = null;
        var minCheckOutDateForSelection = null;
        var maxCheckOutDateForSelection = null;
        roomTypeData = roomTypeCalendarData[formattedProcessingDate];
        if (undefined === roomTypeData || 0 === Object.keys(roomTypeData).length || !roomTypeData.hasOwnProperty('roomTypeStatus')) {
          return {
            minStayDateAfterCheckIn: null,
            maxStayDateAfterCheckIn: null,
            minCheckOutDateForSelection: null,
            maxCheckOutDateForSelection: null
          };
        }
        if (roomTypeData.hasOwnProperty('minStayNights')) {
          processingDate.setDate(processingDate.getDate() + roomTypeData.minStayNights);
          processingDate.setHours(23, 59, 59, 999);
          formattedProcessingDate = $.datepick.formatDate('yyyy-mm-dd', processingDate);
          minStayDateAfterCheckIn = MPHB.Utils.cloneDate(processingDate);
          minStayDateAfterCheckIn.setHours(0, 0, 0, 1);
        }
        if (roomTypeData.hasOwnProperty('maxStayNights')) {
          maxStayDateAfterCheckIn = MPHB.Utils.cloneDate(checkInDate);
          maxStayDateAfterCheckIn.setDate(maxStayDateAfterCheckIn.getDate() + roomTypeData.maxStayNights);
          maxStayDateAfterCheckIn.setHours(23, 59, 59, 999);
        }
        do {
          roomTypeData = roomTypeCalendarData[formattedProcessingDate];
          if (undefined === roomTypeData || 0 === Object.keys(roomTypeData).length || !roomTypeData.hasOwnProperty('roomTypeStatus')) {
            break;
          }
          if (MPHB.calendarHelper.ROOM_STATUS_PAST !== roomTypeData.roomTypeStatus && MPHB.calendarHelper.ROOM_STATUS_EARLIER_MIN_ADVANCE !== roomTypeData.roomTypeStatus && (!roomTypeData.hasOwnProperty('isCheckOutNotAllowed') || !roomTypeData.isCheckOutNotAllowed)) {
            if (null === minCheckOutDateForSelection) {
              minCheckOutDateForSelection = MPHB.Utils.cloneDate(processingDate);
            }
            maxCheckOutDateForSelection = MPHB.Utils.cloneDate(processingDate);
          }
          isStayInAllowedInProcessingDate = roomTypeData.hasOwnProperty('roomTypeStatus') && (!roomTypeData.hasOwnProperty('isStayInNotAllowed') || !roomTypeData.isStayInNotAllowed) && (null === maxStayDateAfterCheckIn || maxStayDateAfterCheckIn.getTime() > processingDate.getTime()) && MPHB.calendarHelper.ROOM_STATUS_BOOKED !== roomTypeData.roomTypeStatus && MPHB.calendarHelper.ROOM_STATUS_NOT_AVAILABLE !== roomTypeData.roomTypeStatus;
          processingDate.setDate(processingDate.getDate() + 1);
          formattedProcessingDate = $.datepick.formatDate('yyyy-mm-dd', processingDate);
        } while (isStayInAllowedInProcessingDate);
        if (null !== minCheckOutDateForSelection) {
          minCheckOutDateForSelection.setHours(0, 0, 0, 1);
        }
        if (null !== maxCheckOutDateForSelection) {
          maxCheckOutDateForSelection.setHours(23, 59, 59, 999);
        }
        return {
          minStayDateAfterCheckIn: minStayDateAfterCheckIn,
          maxStayDateAfterCheckIn: maxStayDateAfterCheckIn,
          minCheckOutDateForSelection: minCheckOutDateForSelection,
          maxCheckOutDateForSelection: maxCheckOutDateForSelection
        };
      }
    };

    /**
     * @class MPHB.Datepicker
     */
    can.Control('MPHB.Datepicker', {}, {
      $datepickerInputElement: null,
      form: null,
      hiddenElement: null,
      roomTypeId: null,
      firstAvailableCheckInDate: null,
      init: function init($datepickerInputElement, args) {
        this.$datepickerInputElement = $datepickerInputElement;
        this.form = args.form;
        this.roomTypeId = args.roomTypeId;
        this.firstAvailableCheckInDate = new Date(args.firstAvailableCheckInDateYmd);

        // setup Hidden Element
        var hiddenElementId = this.element.attr('id') + '-hidden';
        this.hiddenElement = $('#' + hiddenElementId);

        // fix date
        if (this.hiddenElement.val()) {
          var date = $.datepick.parseDate(MPHB._data.settings.dateTransferFormat, this.hiddenElement.val());
          var fixedValue = $.datepick.formatDate(MPHB._data.settings.dateFormat, date);
          this.element.val(fixedValue);
        }
        this.initDatepick();
      },
      initDatepick: function initDatepick() {
        var defaultSettings = {
          dateFormat: MPHB._data.settings.dateFormat,
          altFormat: MPHB._data.settings.dateTransferFormat,
          altField: this.hiddenElement,
          minDate: $.datepick.parseDate(MPHB._data.settings.dateTransferFormat, MPHB._data.today),
          monthsToShow: MPHB._data.settings.numberOfMonthDatepicker,
          firstDay: MPHB._data.settings.firstDay,
          pickerClass: MPHB._data.settings.datepickerClass,
          useMouseWheel: false,
          showSpeed: 0
        };
        var datepickSettings = $.extend(defaultSettings, this.getDatepickSettings());
        this.element.datepick(datepickSettings);
      },
      /**
       *
       * @returns {Object}
       */
      getDatepickSettings: function getDatepickSettings() {
        return {};
      },
      /**
       * @return {Date|null}
       */
      getDate: function getDate() {
        var dateStr = this.element.val();
        var date = null;
        try {
          date = $.datepick.parseDate(MPHB._data.settings.dateFormat, dateStr);
        } catch (e) {
          date = null;
        }
        return date;
      },
      /**
       *
       * @param {string} format Optional. Datepicker format by default.
       * @returns {String} Date string or empty string.
       */
      getFormattedDate: function getFormattedDate(format) {
        if (typeof format === 'undefined') {
          format = MPHB._data.settings.dateFormat;
        }
        var date = this.getDate();
        return date ? $.datepick.formatDate(format, date) : '';
      },
      /**
       * @param {Date} date
       */
      setDate: function setDate(date) {
        this.element.datepick('setDate', date);
      },
      /**
       * @param {string} option
       */
      getOption: function getOption(option) {
        return this.element.datepick('option', option);
      },
      /**
       * @param {string} option
       * @param {mixed} value
       */
      setOption: function setOption(option, value) {
        this.element.datepick('option', option, value);
      },
      /**
       *
       * @returns {Date|null}
       */
      getMinDate: function getMinDate() {
        var minDate = this.getOption('minDate');
        return minDate !== null && minDate !== '' ? MPHB.Utils.cloneDate(minDate) : null;
      },
      /**
       *
       * @returns {Date|null}
       */
      getMaxDate: function getMaxDate() {
        var maxDate = this.getOption('maxDate');
        return maxDate !== null && maxDate !== '' ? MPHB.Utils.cloneDate(maxDate) : null;
      },
      /**
       *
       * @returns {Date|null}
       */
      getMaxAdvanceDate: function getMaxAdvanceDate() {
        var maxAdvanceDate = this.getOption('maxAdvanceDate');
        return maxAdvanceDate ? MPHB.Utils.cloneDate(maxAdvanceDate) : null;
      },
      /**
       *
       * @returns {undefined}
       */
      clear: function clear() {
        this.element.datepick('clear');
      },
      /**
       * @param {Date} date
       * @param {string} format Optional. Default 'yyyy-mm-dd'.
       */
      formatDate: function formatDate(date, format) {
        format = typeof format !== 'undefined' ? format : 'yyyy-mm-dd';
        return $.datepick.formatDate(format, date);
      },
      lock: function lock() {
        $('.datepick-popup').addClass('mphb-loading');
      },
      unlock: function unlock() {
        $('.datepick-popup').removeClass('mphb-loading');
      },
      /**
       * 
       * @param {bool} fullRefresh if true then refresh calendar input as well
       */
      refresh: function refresh() {
        var fullRefresh = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : false;
        $.datepick._update(this.element[0], true);
        if (fullRefresh) {
          $.datepick._updateInput(this.element[0], false);
        }
      }
    });
    MPHB.FlexsliderGallery = can.Control.extend({}, {
      sliderEl: null,
      navSliderEl: null,
      groupId: null,
      init: function init(sliderEl, args) {
        this.sliderEl = sliderEl;
        this.groupId = sliderEl.data('group');
        var navSliderEl = $('.mphb-gallery-thumbnail-slider[data-group="' + this.groupId + '"]');
        if (navSliderEl.length) {
          this.navSliderEl = navSliderEl;
        }
        var self = this;
        $(window).on('load', function () {
          self.initSliders();
        });

        // Load immediately is the window already loaded
        if (document.readyState == 'complete') {
          this.initSliders();
        }
      },
      initSliders: function initSliders() {
        if (this.slidersLoaded) {
          return;
        }
        var sliderAtts = this.sliderEl.data('flexslider-atts');
        if (this.navSliderEl) {
          var navSliderAtts = this.navSliderEl.data('flexslider-atts');
          navSliderAtts['asNavFor'] = '.mphb-flexslider-gallery-wrapper[data-group="' + this.groupId + '"]';
          navSliderAtts['itemWidth'] = this.navSliderEl.find('ul > li img').width();
          sliderAtts['sync'] = '.mphb-gallery-thumbnail-slider[data-group="' + this.groupId + '"]';

          // The slider being synced must be initialized first
          this.navSliderEl.addClass('flexslider mphb-flexslider mphb-gallery-thumbnails-slider').flexslider(navSliderAtts);
        }
        this.sliderEl.addClass('flexslider mphb-flexslider mphb-gallery-slider').flexslider(sliderAtts);
        this.slidersLoaded = true;
      }
    });

    /**
     * @see MPHB.format_price() in admin/admin.js
     */
    MPHB.format_price = function (price, atts) {
      atts = atts || {};
      var defaultAtts = MPHB._data.settings.currency;
      atts = $.extend({
        'trim_zeros': false
      }, defaultAtts, atts);
      price = MPHB.number_format(price, atts['decimals'], atts['decimal_separator'], atts['thousand_separator']);
      var formattedPrice = atts['price_format'].replace('%s', price);
      if (atts['trim_zeros']) {
        var regex = new RegExp('\\' + atts['decimal_separator'] + '0+$|(\\' + atts['decimal_separator'] + '\\d*[1-9])0+$');
        formattedPrice = formattedPrice.replace(regex, '$1');
      }
      var priceHtml = '<span class="mphb-price">' + formattedPrice + '</span>';
      return priceHtml;
    };

    /**
     * @see MPHB.number_format() in admin/admin.js
     */
    MPHB.number_format = function (number, decimals, dec_point, thousands_sep) {
      // + Original by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
      // + Improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
      // +   Bugfix by: Michael White (http://crestidg.com)
      var sign = '',
        i,
        j,
        kw,
        kd,
        km;

      // Input sanitation & defaults
      decimals = decimals || 0;
      dec_point = dec_point || '.';
      thousands_sep = thousands_sep || ',';
      if (number < 0) {
        sign = '-';
        number *= -1;
      }
      i = parseInt(number = (+number || 0).toFixed(decimals)) + '';
      if ((j = i.length) > 3) {
        j = j % 3;
      } else {
        j = 0;
      }
      km = j ? i.substr(0, j) + thousands_sep : '';
      kw = i.substr(j).replace(/(\d{3})(?=\d)/g, '$1' + thousands_sep);
      kd = decimals ? dec_point + Math.abs(number - i).toFixed(decimals).replace(/-/, 0).slice(2) : '';
      return sign + km + kw + kd;
    };

    /**
     * @param {String} action Action name (without prefix "mphb_").
     * @param {Object} data
     * @param {Object} callbacks "success", "error", "complete".
     * @returns {Object} The jQuery XMLHttpRequest object.
     *
     * @since 3.6.0
     */
    MPHB.post = function (action, data, callbacks) {
      action = 'mphb_' + action;
      data = $.extend({
        action: action,
        mphb_nonce: MPHB._data.nonces[action],
        lang: MPHB._data.settings.currentLanguage
      }, data);
      var ajaxArgs = $.extend({
        url: MPHB._data.ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: data
      }, callbacks);
      return $.ajax(ajaxArgs);
    };

    /**
     * @returns {Date}
     *
     * @since 4.11.1
     */
    MPHB.get_today_date = function () {
      return $.datepick.parseDate(MPHB._data.settings.dateTransferFormat, MPHB._data.today);
    };
    MPHB.TermsSwitcher = can.Construct.extend({}, {
      /**
       * @param {Object} element .mphb-checkout-terms-wrapper
       */
      init: function init(element, args) {
        var terms = element.children('.mphb-terms-and-conditions');
        if (terms.length > 0) {
          element.find('.mphb-terms-and-conditions-link').on('click', function (event) {
            event.preventDefault();
            terms.toggleClass('mphb-active');
          });
        }
      }
    });
    MPHB.Utils = can.Construct.extend({
      /**
       *
       * @param {Date} date
       * @returns {String}
       */
      formatDateToCompare: function formatDateToCompare(date) {
        return $.datepick.formatDate('yyyymmdd', date);
      },
      /**
       * @param {Date} date1
       * @param {Date} date2
       * @param {String|Null} operator Optional.
       * @returns {Number|Boolean}
       *
       * @since 3.8.7
       */
      compareDates: function compareDates(date1, date2, operator) {
        var date1 = MPHB.Utils.formatDateToCompare(date1);
        var date2 = MPHB.Utils.formatDateToCompare(date2);
        if (operator != null) {
          switch (operator) {
            case '>':
              return date1 > date2;
              break;
            case '>=':
              return date1 >= date2;
              break;
            case '<':
              return date1 < date2;
              break;
            case '<=':
              return date1 <= date2;
              break;
            case '=':
            case '==':
              return date1 == date2;
              break;
            case '!=':
              return date1 != date2;
              break;
            default:
              return false;
              break;
          }
        } else {
          if (date1 > date2) {
            return 1;
          } else if (date1 < date2) {
            return -1;
          } else {
            return 0;
          }
        }
      },
      /**
       *
       * @param {Date} date
       * @returns {Date}
       */
      cloneDate: function cloneDate(date) {
        return new Date(date.getTime());
      },
      /**
       *
       * @param {Array} arr
       * @returns {Array}
       */
      arrayUnique: function arrayUnique(arr) {
        return arr.filter(function (value, index, self) {
          return self.indexOf(value) === index;
        });
      },
      /**
       *
       * @param {Array} arr
       * @return {number}
       */
      arrayMin: function arrayMin(arr) {
        return Math.min.apply(null, arr);
      },
      /**
       *
       * @param {Array} arr
       * @return {number}
       */
      arrayMax: function arrayMax(arr) {
        return Math.max.apply(null, arr);
      },
      /**
       *
       * @param {Array} a
       * @param {Array} b
       * @return {Array}
       */
      arrayDiff: function arrayDiff(a, b) {
        return a.filter(function (i) {
          return b.indexOf(i) < 0;
        });
      },
      /**
       *
       * @param {mixed} value
       * @param {Array} arr
       * @return {boolean}
       */
      inArray: function inArray(value, arr) {
        return arr.indexOf(value) !== -1;
      }
    }, {});
    MPHB.Gateway = can.Construct.extend({}, {
      amount: 0,
      paymentFee: 0,
      paymentFeeHtml: '',
      paymentDescription: '',
      paymentFields: {},
      mountWrapper: null,
      init: function init(gatewayId, args) {
        this.billingSection = args.billingSection;
        this.gatewayId = gatewayId;
        this.initSettings(args.settings);
      },
      initSettings: function initSettings(settings) {
        var _settings$paymentFee, _settings$paymentFeeH;
        this.amount = settings.amount;
        this.paymentFee = (_settings$paymentFee = settings.paymentFee) !== null && _settings$paymentFee !== void 0 ? _settings$paymentFee : 0;
        this.paymentFeeHtml = (_settings$paymentFeeH = settings.paymentFeeHtml) !== null && _settings$paymentFeeH !== void 0 ? _settings$paymentFeeH : '';
        this.paymentDescription = settings.paymentDescription;
      },
      /**
       * @param {Object} paymentFields
       * @returns {Promise}
       */
      afterProcessing: function afterProcessing(paymentFields) {
        return Promise.resolve(null);
      },
      afterSelection: function afterSelection(newFieldset) {
        this.mountWrapper = newFieldset;
      },
      /**
       * @param {Number} amount The price to pay.
       * @param {Object} customer Maximum information about the customer. See
       *     MPHB.CheckoutForm.getCustomerDetails() for more details.
       * @returns {Promise}
       *
       * @since 3.6.0 added new parameter - amount.
       * @since 3.6.0 added new parameter - customer.
       * @since 3.6.0 changed the return value from Boolean to Promise.
       */
      canSubmit: function canSubmit(amount, customer) {
        return Promise.resolve(true);
      },
      cancelSelection: function cancelSelection() {
        this.mountWrapper = null;
      },
      getGatewayId: function getGatewayId() {
        return this.gatewayId;
      },
      getPaymentField: function getPaymentField(name) {
        var fieldId = 'mphb_' + this.getGatewayId() + '_' + name;
        return this.paymentFields[fieldId] || '';
      },
      getPaymentFields: function getPaymentFields() {
        return this.paymentFields;
      },
      hasPaymentField: function hasPaymentField(name) {
        var fieldId = 'mphb_' + this.getGatewayId() + '_' + name;
        return fieldId in this.paymentFields;
      },
      /**
       * @param {String} name
       * @param {String} value
       *
       * @since 3.6.0
       */
      onInput: function onInput(name, value) {},
      updateData: function updateData(data) {
        this.amount = data.amount;
        this.paymentFee = data.paymentFee;
        this.paymentFeeHtml = data.paymentFeeHtml;
        this.paymentDescription = data.paymentDescription;
      },
      _setPaymentField: function _setPaymentField(name, value) {
        var fieldId = 'mphb_' + this.getGatewayId() + '_' + name;
        this.paymentFields[fieldId] = value;
        if (this.mountWrapper !== null) {
          this.mountWrapper.find('#' + fieldId).val(value);
        }
      }
    });

    /**
     * @requires ./gateway.js
     */
    MPHB.BillingSection = can.Control.extend({}, {
      updateBillingFieldsTimeout: null,
      parentForm: null,
      billingFieldsWrapperEl: null,
      gateways: {},
      /** @since 3.6.1 */
      amounts: {},
      lastGatewayId: null,
      init: function init(el, args) {
        this.parentForm = args.form;
        this.billingFieldsWrapperEl = this.element.find('.mphb-billing-fields');
        this.initGateways(args.gateways);
      },
      initGateways: function initGateways(gateways) {
        var self = this;
        $.each(gateways, function (gatewayId, settings) {
          var gatewaySettings = {
            billingSection: self,
            settings: settings
          };
          var gateway = null;
          try {
            switch (gatewayId) {
              case 'braintree':
                gateway = new MPHB.BraintreeGateway(gatewayId, gatewaySettings);
                break;
              case 'beanstream':
                gateway = new MPHB.BeanstreamGateway(gatewayId, gatewaySettings);
                break;
              case 'stripe':
                if (settings.paymentMode === 'payment') {
                  gateway = new MPHB.StripeGateway(gatewayId, gatewaySettings);
                } else {
                  gateway = new MPHB.StripeLegacyGateway(gatewayId, gatewaySettings);
                }
                break;
              default:
                gateway = new MPHB.Gateway(gatewayId, gatewaySettings);
                break;
            }
          } catch (error) {
            console.error(error);
          }
          if (gateway != null) {
            self.gateways[gatewayId] = gateway;
            self.amounts[gatewayId] = settings.amount;
          }
        }); // For each gateway

        this.notifySelectedGateway();
      },
      isEmpty: function isEmpty() {
        return this.element.length === 0;
      },
      getBookingDetails: function getBookingDetails() {
        return this.parentForm.parseFormToJSON();
      },
      getCheckoutForm: function getCheckoutForm() {
        return this.parentForm;
      },
      getPaymentDetails: function getPaymentDetails() {
        var gatewayId = this.getSelectedGatewayId();
        var gateway = this.gateways[gatewayId];
        return {
          gateway_id: gatewayId,
          payment_fields: gateway ? gateway.getPaymentFields() : {}
        };
      },
      getRoomDetails: function getRoomDetails() {
        var bookingDetails = this.getBookingDetails();
        return bookingDetails['mphb_room_details'] || {};
      },
      getRoomTypeIds: function getRoomTypeIds() {
        var roomDetails = this.getRoomDetails();
        var roomTypeIds = [];
        for (var index in roomDetails) {
          var roomTypeId = parseInt(roomDetails[index]['room_type_id']);
          if (!isNaN(roomTypeId) && roomTypeIds.indexOf(roomTypeId) == -1) {
            roomTypeIds.push(roomTypeId);
          }
        }
        return roomTypeIds;
      },
      updateBillingInfo: function updateBillingInfo(el, e) {
        var self = this;
        var gatewayId = el.val();
        this.showPreloader();
        this.billingFieldsWrapperEl.empty().addClass('mphb-billing-fields-hidden');
        clearTimeout(this.updateBillingFieldsTimeout);
        this.updateBillingFieldsTimeout = setTimeout(function () {
          var formData = self.getBookingDetails();
          $.ajax({
            url: MPHB._data.ajaxUrl,
            type: 'GET',
            dataType: 'json',
            data: {
              action: 'mphb_get_billing_fields',
              mphb_nonce: MPHB._data.nonces.mphb_get_billing_fields,
              mphb_gateway_id: gatewayId,
              formValues: formData,
              lang: MPHB._data.settings.currentLanguage
            },
            success: function success(response) {
              if (response.hasOwnProperty('success')) {
                if (response.success) {
                  // Disable previous selected gateway
                  if (self.lastGatewayId) {
                    self.gateways[self.lastGatewayId].cancelSelection();
                  }
                  self.billingFieldsWrapperEl.html(response.data.fields);
                  if (response.data.hasVisibleFields) {
                    self.billingFieldsWrapperEl.removeClass('mphb-billing-fields-hidden');
                  } else {
                    self.billingFieldsWrapperEl.addClass('mphb-billing-fields-hidden');
                  }
                  self.notifySelectedGateway(gatewayId);
                } else {
                  self.showError(response.data.message);
                }
              } else {
                self.showError(MPHB._data.translations.errorHasOccured);
              }
            },
            error: function error(jqXHR) {
              self.showError(MPHB._data.translations.errorHasOccured);
            },
            complete: function complete(jqXHR) {
              self.hidePreloader();
            }
          });
        }, 500);
      },
      '[name="mphb_gateway_id"] change': function nameMphb_gateway_id_change(el, e) {
        this.updateBillingInfo(el, e);
      },
      hideErrors: function hideErrors() {
        this.parentForm.hideErrors();
      },
      showError: function showError(message) {
        this.parentForm.showError(message);
      },
      showPreloader: function showPreloader() {
        this.parentForm.showPreloader();
      },
      hidePreloader: function hidePreloader() {
        this.parentForm.hidePreloader();
      },
      /**
       * @param {String} name
       * @param {String} value
       *
       * @since 3.6.0
       */
      onInput: function onInput(name, value) {
        var gateway = this.getSelectedGateway();
        if (gateway) {
          gateway.onInput(name, value);
        }
      },
      /**
       * @param {Number} amount The price to pay.
       * @param {Object} customer Maximum information about the customer. See
       *     MPHB.CheckoutForm.getCustomerDetails() for more details.
       * @returns {Promise}
       *
       * @since 3.6.0 added new parameter - amount.
       * @since 3.6.0 added new parameter - customer.
       * @since 3.6.0 changed the return value from Boolean to Promise.
       */
      canSubmit: function canSubmit(amount, customer) {
        var gateway = this.getSelectedGateway();
        if (gateway) {
          return gateway.canSubmit(amount, customer);
        } else {
          return Promise.resolve(true);
        }
      },
      /**
       * @param {Object} paymentFields
       * @returns {Promise}
       */
      afterProcessing: function afterProcessing(paymentFields) {
        var gateway = this.getSelectedGateway();
        if (gateway !== null) {
          return gateway.afterProcessing(paymentFields);
        } else {
          return Promise.resolve(null);
        }
      },
      getSelectedGateway: function getSelectedGateway() {
        return this.gateways[this.getSelectedGatewayId()] || null;
      },
      getSelectedGatewayId: function getSelectedGatewayId() {
        var gatewayEl = this.getSelectedGatewayEl();
        if (gatewayEl && gatewayEl.length > 0) {
          return gatewayEl.val();
        } else {
          return '';
        }
      },
      /**
       * @since 3.9.9
       */
      getSelectedGatewayEl: function getSelectedGatewayEl() {
        var gateways = this.element.find('[name="mphb_gateway_id"]');
        if (gateways.length == 1) {
          return gateways;
        } else {
          return gateways.filter(':checked');
        }
      },
      /** @since 3.6.1 */
      getSelectedGatewayAmount: function getSelectedGatewayAmount() {
        var gatewayId = this.getSelectedGatewayId();
        if (this.amounts.hasOwnProperty(gatewayId)) {
          return this.amounts[gatewayId];
        } else {
          return 0;
        }
      },
      getSelectedGatewayPaymentFeeAmount: function getSelectedGatewayPaymentFeeAmount() {
        var gateway = this.getSelectedGateway();
        if (gateway !== null) {
          var _gateway$paymentFee;
          return (_gateway$paymentFee = gateway.paymentFee) !== null && _gateway$paymentFee !== void 0 ? _gateway$paymentFee : 0;
        } else {
          return 0;
        }
      },
      getSelectedGatewayPaymentFeeHtml: function getSelectedGatewayPaymentFeeHtml() {
        var gateway = this.getSelectedGateway();
        if (gateway !== null) {
          var _gateway$paymentFeeHt;
          return (_gateway$paymentFeeHt = gateway.paymentFeeHtml) !== null && _gateway$paymentFeeHt !== void 0 ? _gateway$paymentFeeHt : '';
        } else {
          return '';
        }
      },
      notifySelectedGateway: function notifySelectedGateway(gatewayId) {
        gatewayId = gatewayId || this.getSelectedGatewayId();
        if (gatewayId && this.gateways.hasOwnProperty(gatewayId)) {
          this.gateways[gatewayId].afterSelection(this.billingFieldsWrapperEl);

          // Set up updated value of the country
          var selectedCountry = this.parentForm.getCountry();
          if (selectedCountry !== false) {
            this.gateways[gatewayId].onInput('country', selectedCountry);
          }
        }
        this.lastGatewayId = gatewayId;
      },
      updateGatewaysData: function updateGatewaysData(gatewaysData) {
        var self = this;
        $.each(gatewaysData, function (gatewayId, gatewayData) {
          if (self.gateways.hasOwnProperty(gatewayId)) {
            self.gateways[gatewayId].updateData(gatewayData);
          }
        });
      }
    });
    MPHB.CouponSection = can.Control.extend({}, {
      applyCouponTimeout: null,
      parentForm: null,
      appliedCouponEl: null,
      couponEl: null,
      messageHolderEl: null,
      init: function init(el, args) {
        this.parentForm = args.form;
        this.couponEl = el.find('[name="mphb_coupon_code"]');
        this.appliedCouponEl = el.find('[name="mphb_applied_coupon_code"]');
        this.messageHolderEl = el.find('.mphb-coupon-message');
      },
      '.mphb-apply-coupon-code-button click': function mphbApplyCouponCodeButton_click(el, e) {
        e.preventDefault();
        e.stopPropagation();
        this.clearMessage();
        var couponCode = this.couponEl.val();
        if (!couponCode.length) {
          this.showMessage(MPHB._data.translations.emptyCouponCode);
          return;
        }
        this.appliedCouponEl.val('');
        var self = this;
        this.showPreloader();
        clearTimeout(this.applyCouponTimeout);
        this.applyCouponTimeout = setTimeout(function () {
          var formData = self.parentForm.parseFormToJSON();
          $.ajax({
            url: MPHB._data.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
              action: 'mphb_apply_coupon',
              mphb_nonce: MPHB._data.nonces.mphb_apply_coupon,
              mphb_coupon_code: couponCode,
              formValues: formData,
              lang: MPHB._data.settings.currentLanguage
            },
            success: function success(response) {
              if (response.hasOwnProperty('success')) {
                if (response.success) {
                  self.parentForm.setCheckoutData(response.data);
                  self.couponEl.val('');
                  self.appliedCouponEl.val(response.data.coupon.applied_code);
                  self.showMessage(response.data.coupon.message);
                } else {
                  self.showMessage(response.data.message);
                }
              } else {
                self.showMessage(MPHB._data.translations.errorHasOccured);
              }
            },
            error: function error(jqXHR) {
              self.showMessage(MPHB._data.translations.errorHasOccured);
            },
            complete: function complete(jqXHR) {
              self.hidePreloader();
            }
          });
        }, 500);
      },
      removeCoupon: function removeCoupon() {
        this.appliedCouponEl.val('');
        this.clearMessage();
      },
      showPreloader: function showPreloader() {
        this.parentForm.showPreloader();
      },
      hidePreloader: function hidePreloader() {
        this.parentForm.hidePreloader();
      },
      clearMessage: function clearMessage() {
        this.messageHolderEl.html('').addClass('mphb-hide');
      },
      showMessage: function showMessage(message) {
        this.messageHolderEl.html(message).removeClass('mphb-hide');
      }
    });

    /**
     * @requires ./billing-section.js
     * @requires ./coupon-section.js
     * @required ./guests-chooser.js
     */
    MPHB.CheckoutForm = can.Control.extend({
      myThis: null
    }, {
      priceBreakdownTableEl: null,
      bookBtnEl: null,
      errorsWrapperEl: null,
      preloaderEl: null,
      billingSection: null,
      couponSection: null,
      waitResponse: false,
      updateInfoTimeout: null,
      updateRatesTimeout: null,
      freeBooking: false,
      currentInfoAjax: null,
      total: 0,
      deposit: 0,
      paymentFee: 0,
      /** @since 3.6.0 */
      toPay: 0,
      mode: 'booking',
      // "booking"|"payment". Use "payment" with bookingId in Payment Request.
      bookingId: 0,
      init: function init(el, args) {
        // when we have free booking after checkout submit
        // we do not have checkout form so we do not want to init it
        if (!el.length) return;
        MPHB.CheckoutForm.myThis = this;
        if (args) {
          if (args.bookingId) {
            this.bookingId = args.bookingId;
          }
          if (args.mode) {
            this.mode = args.mode;
          }
        }
        this.bookBtnEl = this.element.find('input[type=submit]');
        this.errorsWrapperEl = this.element.find('.mphb-errors-wrapper');
        this.preloaderEl = this.element.find('.mphb-preloader');
        this.priceBreakdownTableEl = this.element.find('table.mphb-price-breakdown');
        if (MPHB._data.settings.useBilling) {
          this.billingSection = new MPHB.BillingSection(this.element.find('#mphb-billing-details'), {
            'form': this,
            'gateways': MPHB._data.gateways
          });
        }
        if (MPHB._data.settings.useCoupons) {
          this.couponSection = new MPHB.CouponSection(this.element.find('#mphb-coupon-details'), {
            'form': this
          });
        }
        this.element.find('.mphb-room-details').each(function (i, element) {
          new MPHB.GuestsChooser($(element), {
            minAdults: MPHB._data.checkout.min_adults,
            minChildren: MPHB._data.checkout.min_children
          });
        });
        var self = this;
        $('.mphb-room-details').each(function () {
          self.updateRatePrices($(this));
        });
        $('[name="mphb_gateway_id"]').on('change', function (e) {
          self.setPaymentFee();
          self.element[0].dispatchEvent(new CustomEvent('CheckoutDataChanged', {
            detail: {
              total: this.total,
              deposit: this.deposit,
              toPay: this.toPay,
              paymentFee: this.paymentFee
            }
          }));
        });

        // we need this for payment request because it disables mphb_update_checkout_info
        this.setCheckoutData({
          newAmount: MPHB._data.checkout.total,
          depositAmount: MPHB._data.checkout.total,
          gateways: MPHB._data.gateways
        });
        this.updateCheckoutInfo();
      },
      updateCheckoutInfo: function updateCheckoutInfo() {
        var self = this;
        self.hideErrors();
        self.showPreloader();
        clearTimeout(this.updateInfoTimeout);
        this.updateInfoTimeout = setTimeout(function () {
          var data = self.parseFormToJSON();
          self.currentInfoAjax = $.ajax({
            url: MPHB._data.ajaxUrl,
            type: 'GET',
            dataType: 'json',
            data: {
              action: 'mphb_update_checkout_info',
              mphb_nonce: MPHB._data.nonces.mphb_update_checkout_info,
              formValues: data,
              lang: MPHB._data.settings.currentLanguage
            },
            beforeSend: function beforeSend() {
              if (self.currentInfoAjax != null) {
                self.currentInfoAjax.abort();
                self.hideErrors();
              }
            },
            success: function success(response) {
              if (response.hasOwnProperty('success') && response.success && response.data) {
                self.setCheckoutData(response.data);
              }
            },
            error: function error(jqXHR) {
              var _ref, _jqXHR$responseJSON$d, _jqXHR$responseJSON, _jqXHR$responseJSON2;
              self.showError((_ref = (_jqXHR$responseJSON$d = (_jqXHR$responseJSON = jqXHR.responseJSON) === null || _jqXHR$responseJSON === void 0 || (_jqXHR$responseJSON = _jqXHR$responseJSON.data) === null || _jqXHR$responseJSON === void 0 ? void 0 : _jqXHR$responseJSON.message) !== null && _jqXHR$responseJSON$d !== void 0 ? _jqXHR$responseJSON$d : (_jqXHR$responseJSON2 = jqXHR.responseJSON) === null || _jqXHR$responseJSON2 === void 0 || (_jqXHR$responseJSON2 = _jqXHR$responseJSON2.data) === null || _jqXHR$responseJSON2 === void 0 ? void 0 : _jqXHR$responseJSON2.errorMessage) !== null && _ref !== void 0 ? _ref : MPHB._data.translations.errorHasOccured);
            },
            complete: function complete(jqXHR) {
              self.hidePreloader();
              self.currentInfoAjax = null;
            }
          });
        }, 500);
      },
      setCheckoutData: function setCheckoutData(data) {
        if (MPHB._data.settings.useBilling) {
          this.billingSection.updateGatewaysData(data.gateways);
        }
        this.total = data.newAmount;
        this.toPay = this.total;
        if (data.priceHtml) {
          this.element.find('.mphb-total-price-field').html(data.priceHtml);
        }
        if (data.priceBreakdown) {
          this.priceBreakdownTableEl.replaceWith(data.priceBreakdown);
          this.priceBreakdownTableEl = this.element.find('table.mphb-price-breakdown');
        }
        if (MPHB._data.settings.useBilling) {
          if (data.depositAmount) {
            this.deposit = data.depositAmount;
            this.toPay = this.deposit;
            this.element.find('.mphb-deposit-amount-field').html(data.depositPrice);
          }
          if (data.isFree) {
            this.setFreeMode();
          } else {
            this.unsetFreeMode();
          }
          this.setPaymentFee();
        }
        this.element[0].dispatchEvent(new CustomEvent('CheckoutDataChanged', {
          detail: {
            total: this.total,
            deposit: this.deposit,
            toPay: this.toPay,
            paymentFee: this.paymentFee
          }
        }));
      },
      setPaymentFee: function setPaymentFee() {
        this.paymentFee = this.billingSection.getSelectedGatewayPaymentFeeAmount();
        if (!this.freeBooking && 0 < this.paymentFee) {
          var totalPaymentFeeEl = this.element.find('.mphb-total-price .mphb-payment-fee');
          var depositPaymentFeeEl = this.element.find('.mphb-deposit-amount .mphb-payment-fee');

          // there is no depositPaymentFeeEl in payment request addon
          if (!depositPaymentFeeEl.length || this.total === this.deposit) {
            totalPaymentFeeEl.removeClass('mphb-hide');
            totalPaymentFeeEl.find('.mphb-payment-fee-field').html(this.billingSection.getSelectedGatewayPaymentFeeHtml());
            depositPaymentFeeEl.addClass('mphb-hide');
            depositPaymentFeeEl.find('.mphb-payment-fee-field').html('');
          } else {
            totalPaymentFeeEl.addClass('mphb-hide');
            totalPaymentFeeEl.find('.mphb-payment-fee-field').html('');
            depositPaymentFeeEl.removeClass('mphb-hide');
            depositPaymentFeeEl.find('.mphb-payment-fee-field').html(this.billingSection.getSelectedGatewayPaymentFeeHtml());
          }
        } else {
          this.element.find('.mphb-payment-fee-field').html('');
          this.element.find('.mphb-payment-fee').addClass('mphb-hide');
        }
        this.toPay = this.total === this.deposit ? this.total : this.deposit;
        if (!this.freeBooking) {
          this.toPay += this.paymentFee;
        }
      },
      setFreeMode: function setFreeMode() {
        this.freeBooking = true;
        this.billingSection.element.addClass('mphb-hide');
        this.element.append($('<input />', {
          'type': 'hidden',
          'name': 'mphb_gateway_id',
          'value': 'manual',
          'id': 'mphb-manual-payment-input'
        }));
      },
      unsetFreeMode: function unsetFreeMode() {
        this.freeBooking = false;
        this.billingSection.element.removeClass('mphb-hide');
        this.element.find('#mphb-manual-payment-input').remove();
      },
      updateRatePrices: function updateRatePrices(room) {
        if (!room || !room.length) {
          return;
        }
        var index = parseInt(room.attr('data-index'));

        // Get IDs of all rates for this room
        var rates = room.find('.mphb_sc_checkout-rate');
        var rateIds = $.map(rates, function (rate) {
          return parseInt(rate.value);
        });
        if (rateIds.length <= 1) {
          // Single rate does not show, nothing to update
          return;
        }
        var formData = this.parseFormToJSON();
        var details = formData['mphb_room_details'][index];
        var adults = details.adults || '';
        var children = details.children || '';
        clearTimeout(this.updateRatesTimeout);
        this.updateRatesTimeout = setTimeout(function () {
          $.ajax({
            url: MPHB._data.ajaxUrl,
            type: 'GET',
            dataType: 'json',
            data: {
              action: 'mphb_update_rate_prices',
              mphb_nonce: MPHB._data.nonces.mphb_update_rate_prices,
              rates: rateIds,
              adults: adults,
              children: children,
              check_in_date: formData['mphb_check_in_date'],
              check_out_date: formData['mphb_check_out_date'],
              lang: MPHB._data.settings.currentLanguage
            },
            success: function success(response) {
              if (!response.hasOwnProperty('success')) {
                return;
              }
              var prices = response.data; // {%Rate ID%: %Price HTML%}
              $.each(rates, function (i, rate) {
                var rateId = rate.value;
                if (prices[rateId] == undefined) {
                  return;
                }
                var parent = $(rate).parent().children('strong');
                // Remove old price
                parent.children('.mphb-price').remove();
                // Add new price
                parent.append(prices[rateId]);
              });
            }
          });
        }, 500);
      },
      '.mphb_checkout-guests-chooser change': function mphb_checkoutGuestsChooser_change(el, e) {
        this.updateRatePrices(el.closest('.mphb-room-details'));
        this.updateCheckoutInfo();
      },
      '.mphb_checkout-rate change': function mphb_checkoutRate_change(el, e) {
        this.updateCheckoutInfo();
      },
      '.mphb_checkout-service, .mphb_checkout-service-adults change': function mphb_checkoutService_Mphb_checkoutServiceAdults_change(el, e) {
        this.updateCheckoutInfo();
      },
      '.mphb_checkout-service-quantity input': function mphb_checkoutServiceQuantity_input(el, e) {
        this.updateCheckoutInfo();
      },
      /**
       * @param {Object} element
       * @param {Object} event
       *
       * @since 3.6.0
       */
      'select[name="mphb_country"] change': function selectNameMphb_country_change(element, event) {
        if (this.billingSection != null) {
          var country = $(element).val();
          this.billingSection.onInput('country', country);
        }
      },
      /**
       * @returns {String|Boolean} Country name or FALSE.
       *
       * @since 3.6.0
       */
      getCountry: function getCountry() {
        return this.getCustomerDetail('country');
      },
      // See also assets/js/admin/dev/controls/price-breakdown-ctrl.js
      '.mphb-price-breakdown-expand click': function mphbPriceBreakdownExpand_click(el, e) {
        e.preventDefault();
        $(el).blur(); // Don't save a:focus style on last clicked item
        var tr = $(el).parents('tr.mphb-price-breakdown-group');
        tr.find('.mphb-price-breakdown-rate').toggleClass('mphb-hide');
        tr.nextUntil('tr.mphb-price-breakdown-group').toggleClass('mphb-hide');
        $(el).children('.mphb-inner-icon').toggleClass('mphb-hide');
      },
      hideErrors: function hideErrors() {
        this.errorsWrapperEl.empty().addClass('mphb-hide');
      },
      showError: function showError(message) {
        this.errorsWrapperEl.html(message).removeClass('mphb-hide');
      },
      showPreloader: function showPreloader() {
        this.waitResponse = true;
        this.bookBtnEl.attr('disabled', 'disabled');
        this.preloaderEl.removeClass('mphb-hide');
      },
      hidePreloader: function hidePreloader() {
        this.waitResponse = false;
        this.bookBtnEl.removeAttr('disabled');
        this.preloaderEl.addClass('mphb-hide');
      },
      parseFormToJSON: function parseFormToJSON() {
        if (this.element && this.element.length > 0) {
          return this.element.serializeJSON();
        }
        return false;
      },
      isRequiredField: function isRequiredField(name) {
        var field = document.getElementById('mphb_' + name);
        if (field !== null) {
          return field.required;
        } else {
          return false;
        }
      },
      /**
       * @param {String} fieldName
       * @returns {Object}
       *
       * @since 3.7.2
       */
      getCustomerDetail: function getCustomerDetail(fieldName) {
        var fieldElement = this.element.find('#mphb_' + fieldName);
        if (fieldElement.length > 0) {
          return fieldElement.val();
        } else {
          return false;
        }
      },
      /**
       * @returns {Object} The maximum information about the customer: name,
       *     email, full address (if required) etc.
       *
       * @since 3.6.0
       */
      getCustomerDetails: function getCustomerDetails() {
        var customer = {
          email: '',
          first_name: '',
          last_name: ''
        };
        var customerFields = ['name', 'first_name', 'last_name', 'email', 'phone', 'country', 'address1', 'city', 'state', 'zip'];
        var self = this;
        customerFields.forEach(function (fieldName) {
          var customerDetail = self.getCustomerDetail(fieldName);
          if (customerDetail !== false) {
            customer[fieldName] = customerDetail;
          }
        });
        if (!customer.name) {
          var name = customer.first_name + ' ' + customer.last_name;
          customer.name = name.trim();
        }
        return customer;
      },
      getCustomerEmail: function getCustomerEmail() {
        return this.getCustomerDetail('email');
      },
      /**
       * @since 3.6.1
       */
      getToPayAmount: function getToPayAmount() {
        var toPay = this.toPay;
        if (toPay == 0) {
          toPay = this.billingSection.getSelectedGatewayAmount();
        }
        return toPay;
      },
      /**
       * @since 3.6.0 added support of promises.
       */
      'submit': function submit(el, e) {
        if (this.waitResponse) {
          return false;
        }
        this.hideErrors();
        this.showPreloader(); // waitResponse = true

        var bookingDetails = this._parseBookingDetails();
        var isDoingPayment = MPHB._data.settings.useBilling && !this.freeBooking;
        var self = this;

        // Start request
        var submitPromise = null;
        if (isDoingPayment) {
          submitPromise = this.billingSection.canSubmit(this.getToPayAmount(), bookingDetails.customer);
        } else {
          submitPromise = Promise.resolve(true);
        }
        submitPromise.then(function (canSubmit) {
          if (!canSubmit) {
            // No error message here. It is displayed in the billing or
            // gateway section.
            throw new Error();
          }
          if (self.mode === 'booking') {
            // Submit booking + payment
            return self._submitCheckout(bookingDetails);
          } else {
            // Submit payment only
            var paymentDetails = bookingDetails['payment_details'];

            // Need custom fields for Payment Request: request_type, request_amount
            paymentDetails['custom_fields'] = bookingDetails['custom_fields'];
            return MPHB.restApiHelper.submitPayment(paymentDetails);
          }
        }).then(function (response) {
          var finishPromise = null;
          if (isDoingPayment) {
            var paymentFields = response.payment_fields || {};
            finishPromise = self.billingSection.afterProcessing(paymentFields);
          } else {
            finishPromise = Promise.resolve(null);
          }

          // Return REST response
          return finishPromise.then(function () {
            return response;
          });
        }).then(function (response) {
          // Show success message
          var successMessage = response.success_message;
          if (response.redirect_url) {
            successMessage += ' ' + '<span class="mphb-preloader"></span>';
          }
          self.element.html('<p class="mphb_checkout-success-reservation-message">' + successMessage + '</p>');
          self.element[0].scrollIntoView();

          // Redirect?
          if (response.redirect_url) {
            window.location.href = response.redirect_url;
          }
        })["catch"](function (error) {
          // Cancel block
          self.hidePreloader(); // waitResponse = false

          // Show error message
          if (error.message !== '') {
            self.showError(error.message);
          }
        });

        // Wait for response(s)
        return false;
      },
      '#mphb-price-details .mphb-remove-coupon click': function mphbPriceDetails_MphbRemoveCoupon_click(el, e) {
        e.preventDefault();
        e.stopPropagation();
        if (MPHB._data.settings.useCoupons) {
          this.couponSection.removeCoupon();
          this.updateCheckoutInfo();
        }
      },
      _buildFormData: function _buildFormData(bookingDetails) {
        if (!bookingDetails) {
          bookingDetails = this._parseBookingDetails();
        }
        var formData = new FormData();
        for (var key in bookingDetails) {
          var data = bookingDetails[key];
          if (key === 'custom_fields') {
            for (var field in data) {
              var value = data[field];
              if (value !== '') {
                // Example: customer_fields[mphb_custom_field_name]
                formData.append("customer_fields[".concat(field, "]"), value);
              }
            }
          } else if (key === 'customer') {
            for (var field in data) {
              var value = data[field];
              if (value !== '') {
                // Example: customer_fields[mphb_first_name]
                formData.append("customer_fields[mphb_".concat(field, "]"), value);
              }
            }
          } else if (key === 'files') {
            for (var field in data) {
              formData.append(field, data[field]);
            }
          } else if (key === 'note') {
            // customer_fields[mphb_note]
            formData.append("customer_fields[mphb_".concat(key, "]"), data);
          } else if (key === 'payment_details') {
            for (var field in data) {
              if (field !== 'payment_fields') {
                // Example: payment_details[gateway_id]
                formData.append("".concat(key, "[").concat(field, "]"), data[field]);
              } else {
                for (var nestedField in data[field]) {
                  // Example: payment_details[payment_fields][...]
                  formData.append("".concat(key, "[").concat(field, "][").concat(nestedField, "]"), data[field][nestedField]);
                }
              }
            }
          } else if (key === 'room_details') {
            for (var i in data) {
              var roomDetails = data[i];
              for (var field in roomDetails) {
                if (field !== 'services') {
                  // Example: room_details[0][adults]
                  formData.append("".concat(key, "[").concat(i, "][").concat(field, "]"), roomDetails[field]);
                } else {
                  var services = roomDetails[field];
                  for (var j in services) {
                    var serviceDetails = services[j];
                    for (var serviceField in serviceDetails) {
                      // Example: room_details[0][services][1][quantity]
                      formData.append("".concat(key, "[").concat(i, "][").concat(field, "][").concat(j, "][").concat(serviceField, "]"), serviceDetails[serviceField]);
                    }
                  }
                }
              }
            }
          } else {
            // check_in_date, note etc.
            formData.append(key, data);
          }
        }
        return formData;
      },
      _parseBookingDetails: function _parseBookingDetails() {
        var formData = this.parseFormToJSON();

        // Parse rooms
        var roomDetails = [];
        if (_typeof(formData['mphb_room_details']) === 'object') {
          var minAdults = MPHB._data.checkout.min_adults;
          var minChildren = MPHB._data.checkout.min_children;
          for (var roomIndex in formData['mphb_room_details']) {
            var roomData = formData['mphb_room_details'][roomIndex];
            var adults = roomData['adults'] ? parseInt(roomData['adults']) : minAdults;
            var children = roomData['children'] ? parseInt(roomData['children']) : minChildren;
            var rateId = roomData['rate_id'] ? parseInt(roomData['rate_id']) : 0;
            var roomId = roomData['room_id'] ? parseInt(roomData['room_id']) : 0;
            var roomTypeId = roomData['room_type_id'] ? parseInt(roomData['room_type_id']) : 0;
            if (!adults || isNaN(children) || !rateId || isNaN(roomId) || !roomTypeId) {
              continue;
            }
            var services = [];
            if (_typeof(roomData['services']) === 'object') {
              for (var serviceIndex in roomData['services']) {
                var serviceData = roomData['services'][serviceIndex];
                var id = serviceData['id'] ? parseInt(serviceData['id']) : 0;
                var guests = serviceData['adults'] ? parseInt(serviceData['adults']) : 0;
                var quantity = serviceData['quantity'] ? parseInt(serviceData['quantity']) : 1;
                if (!id || !guests || !quantity) {
                  continue;
                }
                services.push({
                  id: id,
                  adults: guests,
                  quantity: quantity
                });
              }
            }
            roomDetails.push({
              adults: adults,
              children: children,
              guest_name: roomData['guest_name'] || '',
              rate_id: rateId,
              room_id: roomId,
              room_type_id: roomTypeId,
              services: services
            });
          }
        }
        var bookingDetails = {
          check_in_date: formData['mphb_check_in_date'] || '',
          check_out_date: formData['mphb_check_out_date'] || '',
          checkout_id: formData['mphb-checkout-id'] || '',
          coupon_code: formData['mphb_applied_coupon_code'] || '',
          custom_fields: {},
          customer: this.getCustomerDetails(),
          files: {},
          lang: MPHB._data.settings.currentLanguage,
          note: formData['mphb_note'] || '',
          room_details: roomDetails
        };

        // Add payment details
        if (MPHB._data.settings.useBilling && !this.freeBooking) {
          bookingDetails['payment_details'] = this.billingSection.getPaymentDetails();
          if (this.mode === 'payment') {
            bookingDetails['payment_details']['amount'] = this.getToPayAmount();
            if (this.bookingId !== 0) {
              bookingDetails['payment_details']['booking_id'] = this.bookingId;
            }
          }

          // If only the WooCommerce payment method is available and option
          // "Hide the payment method description on the checkout page..." is
          // on, the billing section block is missing, but the hidden field
          // "mphb_gateway_id" is present with a pre-selected value
          if (this.billingSection.isEmpty()) {
            bookingDetails['payment_details']['gateway_id'] = formData['mphb_gateway_id'] || '';
          }
        }

        // Parse custom fields
        var knownFields = ['mphb_applied_coupon_code', 'mphb_check_in_date', 'mphb_check_out_date', 'mphb-checkout-id', 'mphb-checkout-nonce', 'mphb_coupon_code', 'mphb_gateway_id', 'mphb_new_booking_status', 'mphb_note', 'mphb_room_details'];
        for (var fieldName in formData) {
          var unprefixedName = fieldName.replace('mphb_', '');
          if (knownFields.includes(fieldName) || fieldName.indexOf('mphb') !== 0 || unprefixedName in bookingDetails['customer']) {
            continue;
          }
          bookingDetails['custom_fields'][fieldName] = formData[fieldName];
        }

        // Parse files
        var $fileInputs = this.element.find('input[type="file"]');
        if ($fileInputs.length > 0) {
          $fileInputs.each(function (i, input) {
            if (input.files.length > 0) {
              bookingDetails['files'][input.name] = input.files[0];
            }
          });
        }
        return bookingDetails;
      },
      _setField: function _setField(name, value) {
        var inputId = 'mphb_' + name;
        var $input = this.element.find('#' + inputId);
        if ($input.length > 0) {
          $input.val(value);
        } else {
          this.element.append($('<input>', {
            id: inputId,
            name: inputId,
            type: 'hidden',
            value: value
          }));
        }
      },
      _submitCheckout: function _submitCheckout(bookingDetails) {
        return MPHB.restApiHelper.submitCheckout(this._buildFormData(bookingDetails));
      }
    });

    /**
     * @requires ./checkout-form.js
     */
    MPHB.AdminCheckoutForm = MPHB.CheckoutForm.extend({}, {
      _parseBookingDetails: function _parseBookingDetails() {
        var bookingDetails = this._super();

        // Get status
        var status = this.element.find('select[name="mphb_new_booking_status"]');
        if (status.length !== 0) {
          bookingDetails['status'] = status.val();
        }
        return bookingDetails;
      },
      _submitCheckout: function _submitCheckout(bookingDetails) {
        return MPHB.restApiHelper.submitAdminCheckout(this._buildFormData(bookingDetails));
      }
    });

    /**
     *
     * @requires ./gateway.js
     */
    MPHB.BeanstreamGateway = MPHB.Gateway.extend({}, {
      scriptUrl: '',
      isCanSubmit: false,
      loadHandler: null,
      validityHandler: null,
      tokenRequestHandler: null,
      tokenUpdatedHandler: null,
      initSettings: function initSettings(settings) {
        this._super(settings);
        this.scriptUrl = settings.scriptUrl || 'https://payform.beanstream.com/v1.1.0/payfields/beanstream_payfields.js';
        this.validityHandler = this.validityChanged.bind(this);
        this.tokenRequestHandler = this.tokenRequested.bind(this);
        this.tokenUpdatedHandler = this.tokenUpdated.bind(this);
      },
      canSubmit: function canSubmit(amount, customer) {
        return Promise.resolve(this.isCanSubmit);
      },
      afterSelection: function afterSelection(newFieldset) {
        this._super(newFieldset);
        if (newFieldset.length > 0) {
          var script = document.createElement('script');
          // <script> must have id "fields-script" or it will fail to init
          script.id = 'payfields-script';
          script.src = this.scriptUrl;
          // controlled in /vendors/beanstream-sdk/js/beanstream_payfields.js:1238
          // script.dataset.submitform = 'true';
          // Use async load only. Otherwise the script will wait infinitely for window.load event
          script.dataset.async = 'true';

          // Create new handler for Beanstream "loaded" (inited) event
          if (this.loadHandler != null) {
            $(document).off('beanstream_payfields_loaded', this.loadHandler);
          }
          this.loadHandler = function (data) {
            $('[data-beanstream-id]').appendTo(newFieldset);
          };
          $(document).on('beanstream_payfields_loaded', this.loadHandler);
          newFieldset.append(script);
          newFieldset.removeClass('mphb-billing-fields-hidden');
        }

        // See all available events: https://github.com/Beanstream/checkoutfields#payfields-
        $(document).on('beanstream_payfields_inputValidityChanged', this.validityHandler).on('beanstream_payfields_tokenRequested', this.tokenRequestHandler).on('beanstream_payfields_tokenUpdated', this.tokenUpdatedHandler);
      },
      cancelSelection: function cancelSelection() {
        this._super();
        $(document).off('beanstream_payfields_inputValidityChanged', this.validityHandler).off('beanstream_payfields_tokenRequested', this.tokenRequestHandler).off('beanstream_payfields_tokenUpdated', this.tokenUpdatedHandler);
      },
      validityChanged: function validityChanged(event) {
        var eventDetail = event.eventDetail || event.originalEvent.eventDetail;
        if (!eventDetail.isValid) {
          this.isCanSubmit = false;
        }
      },
      tokenRequested: function tokenRequested(event) {
        this.billingSection.showPreloader();
      },
      tokenUpdated: function tokenUpdated(event) {
        var eventDetail = event.eventDetail || event.originalEvent.eventDetail;
        if (eventDetail.success) {
          this.isCanSubmit = true;
        } else {
          this.isCanSubmit = false;
          this.billingSection.showError(MPHB._data.translations.tokenizationFailure.replace('(%s)', eventDetail.message));
        }
        this.billingSection.hidePreloader();
        if (eventDetail.success) {
          this.paymentFields['singleUseToken'] = eventDetail.token;
          this.billingSection.parentForm.element.submit();
        }
      }
    });

    /**
     *
     * @requires ./gateway.js
     */
    MPHB.BraintreeGateway = MPHB.Gateway.extend({}, {
      clientToken: '',
      checkout: null,
      // Used to remove all fields and events of the Braintree SDK
      initSettings: function initSettings(settings) {
        this._super(settings);
        this.clientToken = settings.clientToken;
      },
      canSubmit: function canSubmit(amount, customer) {
        return Promise.resolve(this.isNonceStored());
      },
      /**
       * @returns {Boolean}
       */
      isNonceStored: function isNonceStored() {
        return this.hasPaymentField('payment_nonce') && this.getPaymentField('payment_nonce') !== '';
      },
      afterSelection: function afterSelection(newFieldset) {
        this._super(newFieldset);
        if (braintree != undefined) {
          var containerId = 'mphb-braintree-container-' + this.clientToken.substr(0, 8);
          newFieldset.append('<div id="' + containerId + '"></div>');
          var self = this;
          braintree.setup(this.clientToken, 'dropin', {
            container: containerId,
            onReady: function onReady(integration) {
              // We can use integration's teardown() method to remove all DOM elements and attached events
              self.checkout = integration;
            },
            onPaymentMethodReceived: function onPaymentMethodReceived(response) {
              self._setPaymentField('payment_nonce', response.nonce);
              self.billingSection.parentForm.element.submit();
              self.billingSection.showPreloader();
            }
          });
          newFieldset.removeClass('mphb-billing-fields-hidden');
        }
      },
      cancelSelection: function cancelSelection() {
        this._super();
        if (this.checkout != null) {
          var self = this;
          this.checkout.teardown(function () {
            self.checkout = null; // braintree.setup() can safely be run again
          });
        }
      }
    });

    /**
     * @since 3.7.2
     */
    MPHB.GuestsChooser = can.Control.extend({}, {
      $adultsChooser: null,
      $childrenChooser: null,
      minAdults: 0,
      minChildren: 0,
      maxAdults: 0,
      maxChildren: 0,
      totalCapacity: 0,
      init: function init(element, args) {
        var $selects = element.find('.mphb_checkout-guests-chooser');
        if ($selects.length < 2) {
          return;
        }
        this.$adultsChooser = $($selects[0]);
        this.$childrenChooser = $($selects[1]);
        this.minAdults = args.minAdults;
        this.minChildren = args.minChildren;
        this.maxAdults = parseInt(this.$adultsChooser.data('max-allowed'));
        this.maxChildren = parseInt(this.$childrenChooser.data('max-allowed'));
        this.totalCapacity = parseInt($selects.data('max-total'));
        if (this.maxAdults + this.maxChildren > this.totalCapacity) {
          this.$adultsChooser.on('change', this.limitChildren.bind(this));
        }
      },
      limitChildren: function limitChildren() {
        var adults = this.$adultsChooser.val();
        var maxChildren = this.findMax(adults, this.minChildren, this.maxChildren);
        this.limitOptions(this.$childrenChooser, this.minChildren, maxChildren, adults);
      },
      findMax: function findMax(oppositeValue, defaultMin, defaultMax) {
        var maxValue = this.totalCapacity;
        if (oppositeValue !== '') {
          maxValue = this.totalCapacity - oppositeValue;

          // Don't make less than min possible number of adults/children
          maxValue = Math.max(defaultMin, maxValue);
        }

        // Don't make bigger than max possible number of adults/children
        return Math.min(maxValue, defaultMax);
      },
      limitOptions: function limitOptions($select, min, max, oppositeValue) {
        var maxValue = min;

        // Remove all options bigger than %max%
        $select.children().each(function (i, element) {
          var value = element.value;
          if (value !== '') {
            value = parseInt(value);
            if (value > max) {
              $(element).remove();
            } else if (value > maxValue) {
              maxValue = value;
            }
          }
        });

        // Fill options up to %max%
        for (var i = maxValue + 1; i <= max; i++) {
          var $option = jQuery('<option value="' + i + '">' + i + '</option>');
          $select.append($option);
        }

        // Reset selection (select "— Select —")
        if (oppositeValue !== '') {
          $select.children(':selected').prop('selected', false);
        }
      }
    });
    (function ($) {
      $('#mphb-render-checkout-login').click(function (e) {
        e.preventDefault();
        e.stopPropagation();
        var form = $(this).parents('.mphb-login-form-wrap').find('.mphb-login-form');
        if (form.hasClass('mphb-hide')) {
          form.removeClass('mphb-hide');
        } else {
          form.addClass('mphb-hide');
        }
      });
    })(jQuery);

    /**
     * @requires ./gateway.js
     *
     * @since 6.0.0
     */
    MPHB.StripeGateway = MPHB.Gateway.extend({
      // https://docs.stripe.com/upgrades#api-versions (Breaking changes)
      // https://docs.stripe.com/changelog#2025-06-30.basil (Version changelog)
      API_VERSION: '2025-06-30.basil'
    }, {
      // Settings (in addition to "amount" and "paymentDescription" in Gateway)
      currency: 'EUR',
      locale: 'auto',
      isCountryRequired: MPHB._data.settings.countryRequired,
      isFullAddressRequired: MPHB._data.settings.fullAddressRequired,
      isManualCapture: false,
      paymentsConfigurationId: '',
      publicKey: '',
      returnUrl: '',
      // API & controls
      stripe: null,
      elements: null,
      control: null,
      // Elements
      errorsWrapper: null,
      /**
       * What we know about the customer at the start of the page. Generally
       * it's an empty object (on Checkout Page). But on Payment Request
       * Checkout page, when we already have the booking and customer
       * information, this object is set with some basic information required
       * for the script.
       */
      defaultCustomer: null,
      init: function init(gatewayId, args) {
        this._super(gatewayId, args); // initSettings()

        // https://docs.stripe.com/js/elements_object/create
        this.stripe = Stripe(this.publicKey, {
          apiVersion: MPHB.StripeGateway.API_VERSION
        });
      },
      initSettings: function initSettings(settings) {
        this._super(settings);
        this.currency = settings.currency;
        this.locale = settings.locale;
        this.isManualCapture = settings.isManualCapture;
        this.paymentsConfigurationId = settings.paymentsConfigurationId;
        this.publicKey = settings.publicKey;
        this.returnUrl = settings.returnUrl;

        // See StripeGateway::getCheckoutData()
        this.defaultCustomer = settings.customer;
      },
      /**
       * @param {Object} paymentFields
       * @returns {Promise}
       */
      afterProcessing: function afterProcessing(paymentFields) {
        var paymentIntent = paymentFields.payment_intent;
        if (paymentIntent && paymentIntent.status === 'requires_action') {
          // For some payment methods Stripe redirects user to an intermediate
          // page to authorize the payment (without resolving the promise)

          // https://docs.stripe.com/js/payment_intents/handle_next_action
          return this.stripe.handleNextAction({
            clientSecret: paymentFields.payment_intent.client_secret
          });
        } else {
          return Promise.resolve(null);
        }
      },
      afterSelection: function afterSelection(mountWrapper) {
        this._super(mountWrapper);
        this._mount(mountWrapper);
      },
      cancelSelection: function cancelSelection() {
        this._super();
        this.control.destroy();
        this.control = null;
        this.errorsWrapper = null;
      },
      canSubmit: function canSubmit(amount, customer) {
        this._disableControls();
        this._hideErrors();
        var self = this;

        // https://docs.stripe.com/js/elements/submit
        return this.elements.submit() // Validate and submit billing fields
        .then(function (result) {
          if (result.error) {
            throw new Error(result.error.message);
          }

          // https://docs.stripe.com/js/confirmation_tokens/create_confirmation_token
          return self.stripe.createConfirmationToken({
            elements: self.elements,
            params: {
              payment_method_data: {
                billing_details: self._toBillingDetails(customer)
              },
              return_url: self.returnUrl
            }
          });
        }).then(function (result) {
          if (result.error) {
            throw new Error(result.error.message);
          }
          self._setPaymentField('payment_method', 'payment');
          self._setPaymentField('confirmation_token_id', result.confirmationToken.id);
          self._enableControls();
          return true;
        })["catch"](function (error) {
          self._showError(error.message);
          self._enableControls();
          return false;
        });
      },
      updateData: function updateData(data) {
        this._super(data);
        if (this.amount > 0 && this.elements !== null) {
          this.elements.update({
            amount: this._convertToSmallestUnit(this.amount),
            currency: this.currency.toLowerCase()
          });
        }
      },
      _convertToSmallestUnit: function _convertToSmallestUnit(amount, currency) {
        if (!currency) {
          currency = this.currency;
        }

        // See all currencies (presented as links):
        //     https://docs.stripe.com/currencies#presentment-currencies
        switch (currency) {
          // Zero decimal currencies
          case 'BIF':
          case 'CLP':
          case 'DJF':
          case 'GNF':
          case 'JPY':
          case 'KMF':
          case 'KRW':
          case 'MGA':
          case 'PYG':
          case 'RWF':
          case 'UGX':
          case 'VND':
          case 'VUV':
          case 'XAF':
          case 'XOF':
          case 'XPF':
            return Math.floor(amount);
          // Remove cents

          default:
            return Math.round(amount * 100);
          // In cents
        }
      },
      _disableControls: function _disableControls() {
        if (this.control !== null) {
          this.control.update({
            readOnly: true
          });
        }
      },
      _enableControls: function _enableControls() {
        if (this.control !== null) {
          this.control.update({
            readOnly: false
          });
        }
      },
      _hideErrors: function _hideErrors() {
        this.errorsWrapper.addClass('mphb-hide').text('');
      },
      _mount: function _mount(mountWrapper) {
        mountWrapper.append('<section id="mphb-stripe-payment-container" class="mphb-stripe-payment-container">' + '<div class="mphb-stripe-payment-fields payment">' + '<fieldset>' + '<div id="mphb-stripe-payment-element" class="mphb-stripe-element"></div>' + '</fieldset>' + '</div>' + '<div id="mphb-stripe-errors"></div>' + '</section>');
        this.errorsWrapper = mountWrapper.find('#mphb-stripe-errors');
        if (this.elements === null) {
          // https://docs.stripe.com/js/elements_object/create_without_intent
          this.elements = this.stripe.elements({
            amount: this._convertToSmallestUnit(this.amount),
            captureMethod: this.isManualCapture ? 'manual' : 'automatic_async',
            currency: this.currency.toLowerCase(),
            locale: this.locale,
            mode: 'payment',
            paymentMethodConfiguration: this.paymentsConfigurationId
          });
        }

        // Create control:
        //     https://docs.stripe.com/js/elements_object/create_payment_element
        var checkoutForm = MPHB.CheckoutForm.myThis;
        var controlOptions = {
          fields: {
            billingDetails: {
              // Keep in mind Hotel Booking Checkout Fields addon
              name: checkoutForm.isRequiredField('name') ? 'never' : 'auto',
              email: 'never',
              // Always required, even in Checkout Fields
              phone: checkoutForm.isRequiredField('phone') ? 'never' : 'auto'
            }
          }
        };
        if (!this.isCountryRequired && !this.isFullAddressRequired) {
          controlOptions.fields.billingDetails['address'] = 'if_required';
        } else {
          controlOptions.fields.billingDetails['address'] = {
            city: checkoutForm.isRequiredField('city') ? 'never' : 'auto',
            country: checkoutForm.isRequiredField('country') ? 'never' : 'auto',
            line1: checkoutForm.isRequiredField('address1') ? 'never' : 'auto',
            postalCode: checkoutForm.isRequiredField('zip') ? 'never' : 'auto',
            state: checkoutForm.isRequiredField('state') ? 'never' : 'auto'
          };
        }
        this.control = this.elements.create('payment', controlOptions);
        this.control.mount('#mphb-stripe-payment-element');
        var self = this;

        // https://docs.stripe.com/js/element/input_validation
        this.control.on('change', function (event) {
          self._setError(event.error ? event.error.message : '');
        });

        // Show controls
        mountWrapper.removeClass('mphb-billing-fields-hidden');
      },
      _setError: function _setError(message) {
        if (message !== '') {
          this._showError(message);
        } else {
          this._hideErrors();
        }
      },
      _showError: function _showError(message) {
        this.errorsWrapper.html(message).removeClass('mphb-hide');
      },
      _toBillingDetails: function _toBillingDetails(customer) {
        var customerFields = ['name', 'email', 'phone'];
        if (this.isCountryRequired || this.isFullAddressRequired) {
          customerFields.push('country');
        }
        if (this.isFullAddressRequired) {
          customerFields = customerFields.concat(['address1', 'city', 'state', 'zip']);
        }
        var billingDetails = {};
        var addressDetails = {};
        var _iterator = _createForOfIteratorHelper(customerFields),
          _step;
        try {
          for (_iterator.s(); !(_step = _iterator.n()).done;) {
            var field = _step.value;
            var value = customer[field] || this.defaultCustomer[field] || '';
            if (!value) {
              continue;
            }
            switch (field) {
              case 'name':
              case 'email':
              case 'phone':
                billingDetails[field] = value;
                break;
              case 'country':
              case 'city':
              case 'state':
                addressDetails[field] = value;
                break;
              case 'address1':
                addressDetails['line1'] = value;
                break;
              case 'zip':
                addressDetails['postal_code'] = value;
                break;
            }
          }
        } catch (err) {
          _iterator.e(err);
        } finally {
          _iterator.f();
        }
        if (Object.keys(addressDetails).length > 0) {
          billingDetails['address'] = addressDetails;
        }
        return billingDetails;
      }
    });

    /**
     * @requires ./gateway.js
     *
     * @since 3.6.0
     */
    MPHB.StripeLegacyGateway = MPHB.Gateway.extend({}, {
      // Settings
      publicKey: '',
      locale: 'auto',
      currency: 'EUR',
      returnUrl: window.location.href,
      defaultCountry: '',
      paymentDescription: 'Accommodation(s) reservation',
      fullAddressRequired: false,
      i18n: {},
      style: {},
      // API controls
      api: null,
      elements: null,
      cardControl: null,
      sepaDebitControl: null,
      // Own controls
      payments: null,
      customer: null,
      // See canSubmit() and setCustomer()

      /**
       * What we know about the customer at the start of the page. Generally
       * it's an empty object (on Checkout Page). But on Payment Request
       * Checkout page, when we already have the booking and customer
       * information, this object is set with some basic information required
       * for the script.
       *
       * @see MPHB.StripeGateway.setCustomer()
       */
      defaultCustomer: null,
      // Elements
      errorsWrapper: null,
      // Errors
      hasErrors: false,
      undefinedError: MPHB._data.translations.errorHasOccured,
      init: function init(gatewayId, args) {
        this._super(gatewayId, args); // initSettings()

        // https://docs.stripe.com/js/initializing
        this.api = Stripe(this.publicKey);
        // https://docs.stripe.com/js/elements_object/create
        this.elements = this.api.elements({
          locale: this.locale
        });
        this.cardControl = this.elements.create('card', {
          style: this.style,
          hidePostalCode: this.fullAddressRequired
        });
        this.sepaDebitControl = this.elements.create('iban', {
          style: this.style,
          supportedCountries: ['SEPA']
        });
        this.payments = new MPHB.StripeGateway.PaymentMethods(args.settings.paymentMethods, this.defaultCountry, args.settings.currency);
        this.addListeners();
      },
      initSettings: function initSettings(settings) {
        this._super(settings);
        this.publicKey = settings.publicKey;
        this.locale = settings.locale;
        this.currency = settings.currency;
        this.returnUrl = settings.returnUrl;
        this.defaultCountry = settings.defaultCountry;
        this.paymentDescription = settings.paymentDescription;
        this.fullAddressRequired = MPHB._data.settings.fullAddressRequired;

        // See StripeGateway::getCheckoutData()
        this.defaultCustomer = settings.customer;
        this.i18n = settings.i18n;
        this.style = settings.style;
        this.idempotencyKey = $('.mphb_sc_checkout-form').find('input[name="' + settings.idempotencyKeyField + '"]').val();
      },
      addListeners: function addListeners() {
        var onChange = this.onChange.bind(this);
        this.cardControl.on('change', onChange);
        this.sepaDebitControl.on('change', onChange);
      },
      onChange: function onChange(event) {
        if (event.error) {
          this.showError(event.error.message);
          this.hasErrors = true;
        } else {
          this.hideErrors();
          this.hasErrors = false;
        }
      },
      onInput: function onInput(name, value) {
        if ('country' === name) {
          this.payments.selectCountry(value);
        }
      },
      afterSelection: function afterSelection(mountWrapper) {
        this._super(mountWrapper);
        mountWrapper.append(this.mountHtml());
        this.errorsWrapper = mountWrapper.find('#mphb-stripe-errors');

        // Mount all controls
        this.cardControl.mount('#mphb-stripe-card-element');
        if (this.payments.isEnabled('sepa_debit')) {
          this.sepaDebitControl.mount('#mphb-stripe-iban-element');
        }

        // Mount payments control
        this.payments.mount(mountWrapper);
        var self = this;
        this.payments.inputs.on('change', function () {
          // Clear previous control
          switch (self.payments.currentPayment) {
            case 'card':
              self.cardControl.clear();
              break;
            case 'sepa_debit':
              self.sepaDebitControl.clear();
              break;
          }

          // Select new control
          self.payments.selectPayment(this.value);
        });

        // Unhide elements
        mountWrapper.removeClass('mphb-billing-fields-hidden');
      },
      cancelSelection: function cancelSelection() {
        this._super();
        this.errorsWrapper = null;

        // Unmount all controls
        this.cardControl.unmount();
        if (this.payments.isEnabled('sepa_debit')) {
          this.sepaDebitControl.unmount();
        }

        // Unmount payments control
        this.payments.unmount();
      },
      canSubmit: function canSubmit(amount, customer) {
        if (this.hasErrors) {
          return Promise.resolve(false);
        }
        this.setCustomer(customer);
        return this.createPaymentMethod().then(this.createPaymentIntent.bind(this, amount)).then(this.confirmPayment.bind(this)).then(this.handleStripeErrors.bind(this)).then(this.completePayment.bind(this));
      },
      setCustomer: function setCustomer(customerData) {
        var customer = $.extend({}, customerData); // Clone object

        // Init default fields (use data from StripeGateway::getCheckoutData())
        if (!customer.email) {
          customer.email = this.defaultCustomer.email;
        }
        if (!customer.name) {
          customer.name = this.defaultCustomer.name;
        }

        // Add field "country" if not exists
        if (!customer.hasOwnProperty('country')) {
          customer.country = this.payments.currentCountry;
        }
        this.customer = customer;
      },
      createPaymentMethod: function createPaymentMethod() {
        // https://docs.stripe.com/js/payment_methods/create_payment_method
        // https://docs.stripe.com/api/payment_methods/object#payment_method_object-type
        if ('card' === this.payments.currentPayment) {
          return this.api.createPaymentMethod({
            type: 'card',
            card: this.cardControl,
            billing_details: {
              name: this.customer.name,
              email: this.customer.email
            }
          });
        } else if ('bancontact' === this.payments.currentPayment) {
          return this.api.createPaymentMethod({
            type: 'bancontact',
            billing_details: {
              name: this.customer.name,
              email: this.customer.email
            }
          });
        } else if ('ideal' === this.payments.currentPayment) {
          return this.api.createPaymentMethod({
            type: 'ideal',
            // Stop sending the "bank" parameter (iDEAL 2.0, MPI-13077)
            ideal: {
              bank: null,
              bic: null
            },
            billing_details: {
              name: this.customer.name,
              email: this.customer.email
            }
          });
        } else if ('giropay' === this.payments.currentPayment) {
          return this.api.createPaymentMethod({
            type: 'giropay',
            billing_details: {
              name: this.customer.name,
              email: this.customer.email
            }
          });
        } else if ('sepa_debit' === this.payments.currentPayment) {
          return this.api.createPaymentMethod({
            type: 'sepa_debit',
            sepa_debit: this.sepaDebitControl,
            billing_details: {
              name: this.customer.name,
              email: this.customer.email
            }
          });
        } else if ('klarna' === this.payments.currentPayment) {
          return this.api.createPaymentMethod({
            type: 'klarna',
            billing_details: {
              address: {
                country: this.customer.country
              },
              name: this.customer.name,
              email: this.customer.email
            }
          });
        }
      },
      createPaymentIntent: function createPaymentIntent(amount, paymentMethodData) {
        var self = this;
        return new Promise(function (resolve, reject) {
          MPHB.post('create_stripe_payment_intent', {
            amount: amount,
            description: self.paymentDescription,
            paymentMethodType: paymentMethodData.paymentMethod.type,
            paymentMethodId: paymentMethodData.paymentMethod.id,
            idempotencyKey: self.idempotencyKey,
            roomTypeIds: self.billingSection.getRoomTypeIds(),
            customerEmail: self.billingSection.getCheckoutForm().getCustomerEmail()
          }, {
            success: function success(response) {
              if (response.hasOwnProperty('success') && response.success) {
                resolve({
                  id: response.data.id,
                  clientSecret: response.data.client_secret,
                  paymentMethodId: paymentMethodData.paymentMethod.id
                });
              } else {
                self.showError(self.undefinedError);
                reject(new Error(self.undefinedError));
              }
            },
            error: function error(jqXHR) {
              if (undefined !== jqXHR.responseJSON.data.errorMessage) {
                self.showError(jqXHR.responseJSON.data.errorMessage);
                reject(new Error(jqXHR.responseJSON.data.errorMessage));
              } else {
                self.showError(self.undefinedError);
                reject(new Error(self.undefinedError));
              }
            }
          }); // MPHB.post()
        }); // return new Promise()
      },
      confirmPayment: function confirmPayment(paymentIntentResult) {
        if ('card' === this.payments.currentPayment) {
          // https://docs.stripe.com/js/payment_intents/confirm_card_payment
          return this.api.confirmCardPayment(paymentIntentResult.clientSecret, {
            payment_method: paymentIntentResult.paymentMethodId
          });
        } else if ('bancontact' === this.payments.currentPayment) {
          // https://docs.stripe.com/js/payment_intents/confirm_bancontact_payment
          return this.api.confirmBancontactPayment(paymentIntentResult.clientSecret, {
            payment_method: paymentIntentResult.paymentMethodId,
            return_url: this.returnUrl
          }, {
            handleActions: false
          });
        } else if ('ideal' === this.payments.currentPayment) {
          // https://docs.stripe.com/js/payment_intents/confirm_ideal_payment
          return this.api.confirmIdealPayment(paymentIntentResult.clientSecret, {
            payment_method: paymentIntentResult.paymentMethodId,
            return_url: this.returnUrl
          }, {
            handleActions: false
          });
        } else if ('giropay' === this.payments.currentPayment) {
          // https://docs.stripe.com/js/payment_intents/confirm_giropay_payment
          return this.api.confirmGiropayPayment(paymentIntentResult.clientSecret, {
            payment_method: paymentIntentResult.paymentMethodId,
            return_url: this.returnUrl
          }, {
            handleActions: false
          });
        } else if ('sepa_debit' === this.payments.currentPayment) {
          // https://docs.stripe.com/js/payment_intents/confirm_sepa_debit_payment
          return this.api.confirmSepaDebitPayment(paymentIntentResult.clientSecret, {
            payment_method: paymentIntentResult.paymentMethodId
          });
        } else if ('klarna' === this.payments.currentPayment) {
          // https://docs.stripe.com/js/payment_intents/confirm_klarna_payment
          return this.api.confirmKlarnaPayment(paymentIntentResult.clientSecret, {
            payment_method: paymentIntentResult.paymentMethodId,
            return_url: this.returnUrl
          }, {
            handleActions: false
          });
        }
      },
      handleStripeErrors: function handleStripeErrors(stripeResponse) {
        if (stripeResponse.error) {
          this.showError(stripeResponse.error.message);
          throw new Error(stripeResponse.error.message);
        } else if (stripeResponse.paymentIntent != null) {
          return stripeResponse.paymentIntent;
        }
      },
      completePayment: function completePayment(paymentIntent) {
        this._setPaymentField('payment_method', this.payments.currentPayment);
        this._setPaymentField('payment_intent_id', paymentIntent.id);
        if (paymentIntent.status == 'requires_action' && paymentIntent.next_action.type == 'redirect_to_url') {
          this._setPaymentField('redirect_url', paymentIntent.next_action.redirect_to_url.url);
        }
        return true; // Can submit
      },
      mountHtml: function mountHtml() {
        var html = '<section id="mphb-stripe-payment-container" class="mphb-stripe-payment-container">';
        html += this.methodsHtml();
        html += this.fieldsHtml('card');
        html += this.fieldsHtml('bancontact');
        html += this.fieldsHtml('ideal');
        html += this.fieldsHtml('giropay');
        html += this.fieldsHtml('sepa_debit');
        html += this.fieldsHtml('klarna');
        html += '<div id="mphb-stripe-errors"></div>';
        html += '</section>';
        return html;
      },
      methodsHtml: function methodsHtml() {
        if (this.payments.onlyCardEnabled()) {
          return '';
        }
        var i18n = this.i18n;
        var html = '<nav id="mphb-stripe-payment-methods">';
        html += '<ul>';
        this.payments.forEach(function (payment, paymentMethod, stripePayments) {
          if (!paymentMethod.isEnabled) {
            return; // Don't show disabled methods
          }
          var isSelected = stripePayments.isSelected(payment);
          var activeClass = isSelected ? ' active' : '';
          var checkedAttr = isSelected ? ' checked="checked"' : '';
          html += '<li class="mphb-stripe-payment-method ' + payment + activeClass + '">';
          html += '<label>';
          html += '<input type="radio" name="stripe_payment_method" value="' + payment + '"' + checkedAttr + ' />' + i18n[payment];
          html += '</label>';
          html += '</li>';
        });
        html += '</ul>';
        html += '</nav>';
        return html;
      },
      fieldsHtml: function fieldsHtml(payment) {
        if (!this.payments.isEnabled(payment)) {
          return '';
        }
        var html = '';
        var hideClass = this.payments.isSelected(payment) ? '' : ' mphb-hide';
        html += '<div class="mphb-stripe-payment-fields ' + payment + hideClass + '">';
        html += '<fieldset>';
        switch (payment) {
          case 'card':
            html += this.cardHtml();
            break;
          case 'sepa_debit':
            html += this.ibanHtml();
            break;
          default:
            html += this.redirectHtml();
            break;
        }
        html += '</fieldset>';
        if (payment == 'sepa_debit') {
          html += '<p class="notice">' + this.i18n.iban_policy + '</p>';
        }
        html += '</div>';
        return html;
      },
      cardHtml: function cardHtml() {
        var html = '';
        if (this.payments.onlyCardEnabled()) {
          html += '<label for="mphb-stripe-card-element">' + this.i18n.card_description + '</label>';
        }
        html += '<div id="mphb-stripe-card-element" class="mphb-stripe-element"></div>';
        return html;
      },
      ibanHtml: function ibanHtml() {
        return '<label for="mphb-stripe-iban-element">' + this.i18n.iban + '</label>' + '<div id="mphb-stripe-iban-element" class="mphb-stripe-element"></div>';
      },
      redirectHtml: function redirectHtml() {
        return '<p class="notice">' + this.i18n.redirect_notice + '</p>';
      },
      showError: function showError(message) {
        this.errorsWrapper.html(message).removeClass('mphb-hide');
      },
      hideErrors: function hideErrors() {
        this.errorsWrapper.addClass('mphb-hide').text('');
      }
    });
    MPHB.DirectBooking = can.Control.extend({}, {
      reservationForm: null,
      // form.mphb-booking-form
      elementsToHide: null,
      // Quantity wrappers, price block and reservation section
      quantitySection: null,
      // div.mphb-reserve-room-section
      wrapperWithSelect: null,
      // .mphb-rooms-quantity-wrapper.mphb-rooms-quantity-multiple
      wrapperWithoutSelect: null,
      // .mphb-rooms-quantity-wrapper.mphb-rooms-quantity-single
      priceWrapper: null,
      // .mphb-period-price
      quantitySelect: null,
      // select.mphb-rooms-quantity
      availableLabel: null,
      // span.mphb-available-rooms-count
      typeId: 0,
      init: function init(el, args) {
        this.reservationForm = args.reservationForm;
        this.elementsToHide = el.find('.mphb-reserve-room-section, .mphb-rooms-quantity-wrapper, .mphb-regular-price');
        this.quantitySection = el.find('.mphb-reserve-room-section');
        this.wrapperWithSelect = this.quantitySection.find('.mphb-rooms-quantity-wrapper.mphb-rooms-quantity-multiple');
        this.wrapperWithoutSelect = this.quantitySection.find('.mphb-rooms-quantity-wrapper.mphb-rooms-quantity-single');
        this.priceWrapper = this.quantitySection.find('.mphb-period-price');
        this.quantitySelect = this.quantitySection.find('.mphb-rooms-quantity');
        this.availableLabel = this.quantitySection.find('.mphb-available-rooms-count');

        // TODO: get this from reservation form? and remove input?
        this.typeId = el.find('input[name="mphb_room_type_id"]').val();
        this.typeId = parseInt(this.typeId);
      },
      hideSections: function hideSections() {
        this.elementsToHide.addClass('mphb-hide');
        this.reservationForm.reserveBtnWrapper.removeClass('mphb-hide');
      },
      showSections: function showSections(showPrice) {
        this.reservationForm.reserveBtnWrapper.addClass('mphb-hide');
        this.quantitySection.removeClass('mphb-hide');
        if (showPrice) {
          this.priceWrapper.removeClass('mphb-hide');
        }
      },
      resetQuantityOptions: function resetQuantityOptions(count) {
        this.quantitySelect.empty();
        for (var i = 1; i <= count; i++) {
          var option = '<option value="' + i + '">' + i + '</option>';
          this.quantitySelect.append(option);
        }
        this.quantitySelect.val(1); // Otherwise the last option will be active

        // Also update text "of %d accommodation(-s) available."
        this.availableLabel.text(count);
        if (count > 1) {
          this.wrapperWithSelect.removeClass('mphb-hide');
        } else {
          this.wrapperWithoutSelect.removeClass('mphb-hide');
        }
      },
      setupPrice: function setupPrice(price, priceHtml) {
        this.priceWrapper.children('.mphb-price, .mphb-price-period, .mphb-tax-information').remove();
        if (price > 0 && priceHtml != '') {
          this.priceWrapper.append(priceHtml);
        }
      },
      showError: function showError(errorMessage) {
        this.hideSections();
        this.reservationForm.showError(errorMessage);
      },
      loadAvailabilityAndPriceData: function loadAvailabilityAndPriceData() {
        var checkIn = this.reservationForm.checkInDatepicker.getDate();
        var checkOut = this.reservationForm.checkOutDatepicker.getDate();
        if (!checkIn || !checkOut) return;
        this.reservationForm.clearErrors();
        this.reservationForm.lock();
        var self = this;
        $.ajax({
          url: MPHB._data.ajaxUrl,
          type: 'GET',
          dataType: 'json',
          data: {
            action: 'mphb_get_room_type_availability_data',
            mphb_nonce: MPHB._data.nonces.mphb_get_room_type_availability_data,
            room_type_id: this.typeId,
            check_in_date: $.datepick.formatDate(MPHB._data.settings.dateTransferFormat, checkIn),
            check_out_date: $.datepick.formatDate(MPHB._data.settings.dateTransferFormat, checkOut),
            adults_count: this.reservationForm.getAdults(),
            children_count: this.reservationForm.getChildren(),
            lang: MPHB._data.settings.currentLanguage
          },
          success: function success(response) {
            if (response.success) {
              self.resetQuantityOptions(response.data.freeCount);
              self.setupPrice(response.data.price, response.data.priceHtml);
              self.showSections(response.data.price > 0);
            } else {
              self.showError(response.data.message);
            }
          },
          error: function error(jqXHR) {
            self.showError(MPHB._data.translations.errorHasOccured);
          },
          complete: function complete(jqXHR) {
            self.reservationForm.unlock();
          }
        });
      },
      /**
       * See also MPHB.ReservationForm.onDatepickChange().
       */
      'input.mphb-datepick change': function inputMphbDatepick_change(element, event) {
        this.hideSections();
      },
      '.mphb-reserve-btn click': function mphbReserveBtn_click(element, event) {
        event.preventDefault();
        event.stopPropagation();
        var checkIn = this.reservationForm.checkInDatepicker.getDate();
        var checkOut = this.reservationForm.checkOutDatepicker.getDate();
        if (!checkIn || !checkOut) {
          if (!checkIn) {
            this.showError(MPHB._data.translations.checkInNotValid);
          } else {
            this.showError(MPHB._data.translations.checkOutNotValid);
          }
          this.reservationForm.unlock();
        } else {
          this.loadAvailabilityAndPriceData();
        }
      },
      'input.mphb-datepick, select[name="mphb_children"] change': function inputMphbDatepick_selectNameMphb_children_change(element, event) {
        this.loadAvailabilityAndPriceData();
      },
      'select[name="mphb_adults"] change': function selectNameMphb_adults_change(element, event) {
        // restrict children count according to max capacity and selected adult count
        var childrenSelect = jQuery('select[name="mphb_children"]');
        if (childrenSelect.length && undefined !== childrenSelect.data('max-total')) {
          var minAllowedChildren = parseInt(childrenSelect.data('min-allowed'));
          var maxAllowedChildren = parseInt(childrenSelect.data('max-allowed'));
          var totalCapacity = parseInt(childrenSelect.data('max-total'));
          var selectedAdultsCount = parseInt(element.val());
          var maxChildrenCount = Math.min(Math.max(minAllowedChildren, totalCapacity - selectedAdultsCount), maxAllowedChildren);
          var childrenSelectedCount = parseInt(childrenSelect.val());
          childrenSelect.empty();
          for (var i = minAllowedChildren; i <= maxChildrenCount; i++) {
            var $option = jQuery('<option value="' + i + '"' + (i === childrenSelectedCount ? 'selected="selected"' : '') + '>' + i + '</option>');
            childrenSelect.append($option);
          }
        }
        this.loadAvailabilityAndPriceData();
      }
    });
    MPHB.ReservationForm = can.Control.extend({}, {
      /**
       * @var jQuery
       */
      $formElement: null,
      /**
       * @var MPHB.RoomTypeCheckInDatepicker
       */
      checkInDatepicker: null,
      /**
       * @var MPHB.RoomTypeCheckOutDatepicker
       */
      checkOutDatepicker: null,
      /**
       * @var jQuery
       */
      reserveBtnWrapper: null,
      /**
       * @var jQuery
       */
      errorsWrapper: null,
      /**
       * @var {MPHB.DirectBooking|null}
       */
      directBooking: null,
      /**
       * @var int
       */
      roomTypeId: null,
      init: function init($formElement) {
        this.$formElement = $formElement;
        this.roomTypeId = parseInt(this.$formElement.attr('id').replace(/^booking-form-/, ''));
        this.errorsWrapper = this.$formElement.find('.mphb-errors-wrapper');
        var firstAvailableCheckInDateYmd = this.$formElement.attr('data-first_available_check_in_date');
        if (!firstAvailableCheckInDateYmd) {
          firstAvailableCheckInDateYmd = $.datepick.formatDate('yyyy-mm-dd', new Date());
        }

        // init Check-In Datepicker
        this.checkInDatepicker = new MPHB.RoomTypeCheckInDatepicker(this.$formElement.find('input[type="text"][id^=mphb_check_in_date]'), {
          form: this,
          roomTypeId: '1' == MPHB._data.settings.isDirectBooking ? this.roomTypeId : 0,
          firstAvailableCheckInDateYmd: firstAvailableCheckInDateYmd
        });

        // init Check-Out Datepicker
        this.checkOutDatepicker = new MPHB.RoomTypeCheckOutDatepicker(this.$formElement.find('input[type="text"][id^=mphb_check_out_date]'), {
          form: this,
          roomTypeId: '1' == MPHB._data.settings.isDirectBooking ? this.roomTypeId : 0,
          firstAvailableCheckInDateYmd: firstAvailableCheckInDateYmd
        });
        this.reserveBtnWrapper = this.$formElement.find('.mphb-reserve-btn-wrapper');

        // Init direct booking
        if ('1' == MPHB._data.settings.isDirectBooking) {
          this.directBooking = new MPHB.DirectBooking(this.$formElement, {
            reservationForm: this
          });
        }
        $(window).on('mphb-update-date-room-type-' + this.roomTypeId, this.proxy(function () {
          this.checkInDatepicker.refresh();
          this.checkOutDatepicker.refresh();
        }));
        this.unlock();
      },
      updateCheckOutLimitations: function updateCheckOutLimitations() {
        this.checkOutDatepicker.updateCheckOutLimitations(this.checkInDatepicker.getDate());
      },
      /**
       * @returns {Number|String}
       * @since 3.8.3
       */
      getAdults: function getAdults() {
        var input = this.$formElement.find('[name="mphb_adults"]');
        return input.length > 0 ? parseInt(input.val()) : '';
      },
      /**
       * @returns {Number|String}
       * @since 3.8.3
       */
      getChildren: function getChildren() {
        var input = this.$formElement.find('[name="mphb_children"]');
        return input.length > 0 ? parseInt(input.val()) : '';
      },
      showError: function showError(message) {
        this.clearErrors();
        var errorMessage = $('<p>', {
          'class': 'mphb-error',
          'html': message
        });
        this.errorsWrapper.append(errorMessage).removeClass('mphb-hide');
      },
      clearErrors: function clearErrors() {
        this.errorsWrapper.empty().addClass('mphb-hide');
      },
      lock: function lock() {
        this.element.addClass('mphb-loading');
      },
      unlock: function unlock() {
        this.element.removeClass('mphb-loading');
      },
      /**
       * See also MPHB.DirectBooking["input.mphb-datepick change"].
       */
      onDatepickChange: function onDatepickChange() {
        if (null !== this.directBooking) {
          this.directBooking.hideSections();
        }
      }
    });
    MPHB.RoomTypeCalendar = can.Control.extend({}, {
      roomTypeId: null,
      $calendarElement: null,
      isShowPrices: false,
      isTruncatePrices: true,
      isShowPricesCurrency: false,
      allShownMonthsCount: 1,
      // for clickable calendar
      isClickable: false,
      reservationFormElement: null,
      $reservationFormCheckInElement: null,
      $reservationFormCheckOutElement: null,
      isSyncWithReservationFormInitialised: false,
      isSyncWithReservationFormOn: true,
      lastDrawDate: null,
      isCheckInSelected: false,
      isCheckOutSelected: false,
      minCheckOutDateForSelection: null,
      maxCheckOutDateForSelection: null,
      minStayDateAfterCheckIn: null,
      maxStayDateAfterCheckIn: null,
      init: function init($calendarElement, args) {
        var self = this;
        this.$calendarElement = $calendarElement;
        this.roomTypeId = parseInt(this.$calendarElement.data('roomTypeId'));
        if (undefined !== this.$calendarElement.data('is_show_prices')) {
          this.isShowPrices = Boolean(this.$calendarElement.data('is_show_prices'));
        }
        if (undefined !== this.$calendarElement.data('is_truncate_prices')) {
          this.isTruncatePrices = Boolean(this.$calendarElement.data('is_truncate_prices'));
        }
        if (undefined !== this.$calendarElement.data('is_show_prices_currency')) {
          this.isShowPricesCurrency = Boolean(this.$calendarElement.data('is_show_prices_currency'));
        }
        var monthsToShow = MPHB._data.settings.numberOfMonthCalendar;
        var customMonths = this.$calendarElement.attr('data-monthstoshow');
        if (customMonths) {
          var customArray = customMonths.split(',');
          monthsToShow = customArray.length == 1 ? parseInt(customMonths) : customArray;
        }
        if (Array.isArray(monthsToShow)) {
          this.allShownMonthsCount = parseInt(monthsToShow[0]) * parseInt(monthsToShow[1]);
        } else {
          this.allShownMonthsCount = monthsToShow;
        }

        // check is calendar clickable
        if ('1' == MPHB._data.settings.isDirectBooking) {
          this.reservationFormElement = $('#booking-form-' + this.roomTypeId);
          this.isClickable = 0 < this.reservationFormElement.length;
          if (this.isClickable) {
            this.$reservationFormCheckInElement = this.reservationFormElement.find('input[type="text"][id^=mphb_check_in_date]');
            this.$reservationFormCheckOutElement = this.reservationFormElement.find('input[type="text"][id^=mphb_check_out_date]');
          }
        }
        var firstAvailableCheckInDateYmd = this.$calendarElement.attr('data-first_available_check_in_date');
        if (!firstAvailableCheckInDateYmd) {
          firstAvailableCheckInDateYmd = $.datepick.formatDate('yyyy-mm-dd', new Date());
        }
        var firstAvailableCheckInDate = new Date(firstAvailableCheckInDateYmd);
        MPHB.restApiHelper.getAvailabilityData({
          // We load a day before and day after for availability calculations later
          start_date: new Date(firstAvailableCheckInDate.getFullYear(), firstAvailableCheckInDate.getMonth(), 0),
          end_date: new Date(firstAvailableCheckInDate.getFullYear(), firstAvailableCheckInDate.getMonth() + this.allShownMonthsCount, 1),
          room_type_ids: [this.roomTypeId],
          is_add_prices: this.isShowPrices,
          is_truncate_prices: this.isTruncatePrices,
          is_add_prices_currency: this.isShowPricesCurrency
        }, function () {
          self.$calendarElement.addClass('mphb-loading');
        }, function () {
          self.doAfterNewCalendarDataLoaded(self);
          self.$calendarElement.removeClass('mphb-loading');
        });
        this.$calendarElement.hide().datepick({
          minDate: MPHB.get_today_date(),
          defaultDate: firstAvailableCheckInDate,
          monthsToShow: monthsToShow,
          firstDay: MPHB._data.settings.firstDay,
          pickerClass: MPHB._data.settings.datepickerClass,
          useMouseWheel: false,
          rangeSelect: self.isClickable,
          showSpeed: 0,
          onChangeMonthYear: function onChangeMonthYear(year, month) {
            if (self.isClickable) {
              self.lastDrawDate = $.datepick._getInst(self.$calendarElement).drawDate;
            }
            MPHB.restApiHelper.getAvailabilityData({
              // We load a day before and day after for availability calculations later
              start_date: new Date(year, month - 1, 0),
              end_date: new Date(year, month - 1 + self.allShownMonthsCount, 1),
              room_type_ids: [self.roomTypeId],
              is_add_prices: self.isShowPrices,
              is_truncate_prices: self.isTruncatePrices,
              is_add_prices_currency: self.isShowPricesCurrency
            }, function () {
              self.$calendarElement.addClass('mphb-loading');
            }, function () {
              self.doAfterNewCalendarDataLoaded(self);
              self.$calendarElement.removeClass('mphb-loading');
            });
          },
          onDate: function onDate(date, isCurrentMonth) {
            var _roomTypeCalendarData;
            var roomTypeCalendarData = MPHB.restApiHelper.getCachedAvailabilityData([self.roomTypeId], self.isShowPrices, self.isTruncatePrices, self.isShowPricesCurrency);
            var calendarDateAttributes = MPHB.calendarHelper.getCalendarDateAttributesFromAvailability(1, date, isCurrentMonth, (_roomTypeCalendarData = roomTypeCalendarData[self.roomTypeId]) !== null && _roomTypeCalendarData !== void 0 ? _roomTypeCalendarData : {}, self.isShowPrices);
            if (isCurrentMonth) {
              calendarDateAttributes = self.fillClickableCalendarDateData(calendarDateAttributes, date);
            }
            return calendarDateAttributes;
          },
          onSelect: function onSelect(selectedDates) {
            // do nothing if it is not clickable calendar or
            // if it was click to remove checkin selection
            // because checkout selection is not possible
            if (!self.isClickable || 0 === selectedDates.length) return;
            if (!self.isCheckInSelected || self.isCheckInSelected && self.isCheckOutSelected) {
              var _roomTypeCalendarData2;
              self.isCheckInSelected = true;
              self.isCheckOutSelected = false;

              //self.calculateMinMaxCheckOutDateForSelection(selectedDates[0]);

              var roomTypeCalendarData = MPHB.restApiHelper.getCachedAvailabilityData([self.roomTypeId], self.isShowPrices, self.isTruncatePrices, self.isShowPricesCurrency);
              var result = MPHB.calendarHelper.calculateMinMaxCheckOutDateForSelection(selectedDates[0], (_roomTypeCalendarData2 = roomTypeCalendarData[self.roomTypeId]) !== null && _roomTypeCalendarData2 !== void 0 ? _roomTypeCalendarData2 : {});
              self.minCheckOutDateForSelection = result.minCheckOutDateForSelection;
              self.maxCheckOutDateForSelection = result.maxCheckOutDateForSelection;
              self.minStayDateAfterCheckIn = result.minStayDateAfterCheckIn;
              self.maxStayDateAfterCheckIn = result.maxStayDateAfterCheckIn;
            } else {
              self.isCheckOutSelected = true;
            }

            // do not change first drawing month after selection
            var instance = $.datepick._getInst(self.$calendarElement);
            instance.drawDate = self.lastDrawDate;
            instance.options.setSelectedDatesToStatusBar(instance, selectedDates);
            self.fillReservationFormWithSelectedDates(selectedDates);
          },
          onShow: function onShow(element, instance) {
            // 	remove highlight right after calendar was shown
            // 	to avoide of date highlighting even when mouse pointer not over calendar
            element.find('.datepick-highlight').removeClass('datepick-highlight');
            if (self.isClickable) {
              // save draw date to make sure it will not be change on selection
              self.lastDrawDate = instance.drawDate;
              instance.options.initStatusBar(element, instance);
              instance.options.initSelectionOnHover(element, instance);
            }
          },
          setSelectedDatesToStatusBar: function setSelectedDatesToStatusBar(instance, selectedDates) {
            var selectedDatesText = MPHB._data.translations.selectDates;
            if (self.isCheckInSelected) {
              selectedDatesText = $.datepick.formatDate(MPHB._data.settings.dateFormat, selectedDates[0]);
              if (self.isCheckOutSelected) {
                selectedDatesText += ' - ' + $.datepick.formatDate(MPHB._data.settings.dateFormat, selectedDates[1]);
              }
            }
            instance.options.renderer.picker = '<div class="datepick">' + '<div class="datepick-nav">{link:prev}{link:today}{link:next}</div>{months}' + '<div class="datepick-ctrl"><div class="mphb-calendar__selected-dates">' + selectedDatesText + '</div>{link:clear}</div>' + '<div class="datepick-clear-fix"></div></div>';
          },
          initStatusBar: function initStatusBar(element, instance) {
            // clone object to avoide of other calendars changing
            instance.options.renderer = Object.assign({}, instance.options.renderer);
            instance.options.setSelectedDatesToStatusBar(instance, self.$calendarElement.datepick('getDate'));
            instance.options.commands = Object.assign({}, instance.options.commands);
            instance.options.commands.close.keystroke = {};
            instance.options.commands.clear.keystroke = {
              keyCode: 27,
              altKey: false
            };
            instance.options.commands.clear.action = function (instance) {
              // all commands have common functions for all instances!
              // so we need to take it into account in command action function
              if (instance === $.datepick._getInst(self.$calendarElement) || instance === $.datepick._getInst(self.$reservationFormCheckInElement) || instance === $.datepick._getInst(self.$reservationFormCheckOutElement)) {
                if (self.isCheckInSelected) {
                  // we need to save and set draw date again before refresh
                  // to make sure calendar does not change current first drawn month
                  var currentDrawDate = MPHB.Utils.cloneDate(self.lastDrawDate);
                  self.isCheckInSelected = false;
                  self.isCheckOutSelected = false;
                  self.$calendarElement.datepick('setDate', null);
                  self.$reservationFormCheckInElement.datepick('setDate', null);
                  self.$reservationFormCheckOutElement.datepick('setDate', null);
                  instance.drawDate = currentDrawDate;
                  self.lastDrawDate = currentDrawDate;
                  self.refresh();
                }
              } else {
                instance.elem.datepick('clear');
              }
            };
          },
          initSelectionOnHover: function initSelectionOnHover(element, instance) {
            // mark dates as selected between check-in and potensial check-out on hover
            element.find(instance.get('renderer').daySelector + ' a').hover(function () {
              if (self.isCheckInSelected && !self.isCheckOutSelected) {
                var currentHoverDate = $.datepick.retrieveDate(self.$calendarElement, this),
                  selectedDates = self.$calendarElement.datepick('getDate'),
                  processingDate = MPHB.Utils.cloneDate(selectedDates[0]);
                processingDate.setDate(processingDate.getDate() + 1);
                if (selectedDates[0].getTime() < currentHoverDate.getTime() && self.$calendarElement.datepick('isSelectable', currentHoverDate)) {
                  while (currentHoverDate.getTime() > processingDate.getTime()) {
                    self.$calendarElement.find('.dp' + processingDate.getTime()).not('.mphb-extra-date').addClass('mphb-selected-date');
                    processingDate.setDate(processingDate.getDate() + 1);
                  }
                }
              }
            }, function () {
              if (self.isCheckInSelected && !self.isCheckOutSelected) {
                self.$calendarElement.find('.mphb-selected-date').removeClass('mphb-selected-date');
              }
            });
          }
        }).show();
      },
      doAfterNewCalendarDataLoaded: function doAfterNewCalendarDataLoaded(self) {
        if (self.isCheckInSelected && !self.isCheckOutSelected) {
          var _roomTypeCalendarData3;
          var dates = self.$calendarElement.datepick('getDate');
          var roomTypeCalendarData = MPHB.restApiHelper.getCachedAvailabilityData([self.roomTypeId], self.isShowPrices, self.isTruncatePrices, self.isShowPricesCurrency);
          var result = MPHB.calendarHelper.calculateMinMaxCheckOutDateForSelection(dates[0], (_roomTypeCalendarData3 = roomTypeCalendarData[self.roomTypeId]) !== null && _roomTypeCalendarData3 !== void 0 ? _roomTypeCalendarData3 : {});
          self.minCheckOutDateForSelection = result.minCheckOutDateForSelection;
          self.maxCheckOutDateForSelection = result.maxCheckOutDateForSelection;
          self.minStayDateAfterCheckIn = result.minStayDateAfterCheckIn;
          self.maxStayDateAfterCheckIn = result.maxStayDateAfterCheckIn;
        }
        self.refresh();
        if (self.isClickable && !self.isSyncWithReservationFormInitialised) {
          self.isSyncWithReservationFormInitialised = true;
          self.initSyncWithReservationForm();
        }
      },
      fillClickableCalendarDateData: function fillClickableCalendarDateData(calendarDateData, date) {
        var _formattedDate, _roomTypeCalendarData4;
        if (!this.isClickable) {
          calendarDateData.selectable = false;
          return calendarDateData;
        }
        var roomTypeCalendarData = MPHB.restApiHelper.getCachedAvailabilityData([this.roomTypeId], this.isShowPrices, this.isTruncatePrices, this.isShowPricesCurrency);
        var formattedDate = $.datepick.formatDate('yyyy-mm-dd', date);
        var roomTypeData = (_formattedDate = ((_roomTypeCalendarData4 = roomTypeCalendarData[this.roomTypeId]) !== null && _roomTypeCalendarData4 !== void 0 ? _roomTypeCalendarData4 : {})[formattedDate]) !== null && _formattedDate !== void 0 ? _formattedDate : {};
        if (undefined === roomTypeData || 0 === Object.keys(roomTypeData).length || !roomTypeData.hasOwnProperty('roomTypeStatus')) {
          return calendarDateData;
        }

        // checkIn is not selected yet or both dates are selected already
        // and user can select checkIn date again
        if (!this.isCheckInSelected || this.isCheckInSelected && this.isCheckOutSelected) {
          if (MPHB.calendarHelper.ROOM_STATUS_AVAILABLE === roomTypeData.roomTypeStatus && (!roomTypeData.hasOwnProperty('isCheckInNotAllowed') || !roomTypeData.isCheckInNotAllowed)) {
            calendarDateData.selectable = true;
            calendarDateData.dateClass += ' mphb-selectable-date--check-in';
          } else {
            calendarDateData.selectable = false;
            calendarDateData.dateClass += ' mphb-unselectable-date--check-in';
          }
        } else {
          // checkIn selected but checkOut is not
          if (null !== this.minCheckOutDateForSelection && this.minCheckOutDateForSelection.getTime() <= date.getTime() && (null === this.maxCheckOutDateForSelection || this.maxCheckOutDateForSelection.getTime() >= date.getTime()) && (!roomTypeData.hasOwnProperty('isCheckOutNotAllowed') || !roomTypeData.isCheckOutNotAllowed)) {
            calendarDateData.selectable = true;
            calendarDateData.dateClass += ' mphb-selectable-date--check-out';
          } else {
            calendarDateData.selectable = false;
            calendarDateData.dateClass += ' mphb-unselectable-date--check-out';
          }
          if (null !== this.minStayDateAfterCheckIn && this.minStayDateAfterCheckIn.getTime() > date.getTime()) {
            calendarDateData.title += '\n' + MPHB._data.translations.lessThanMinDaysStay;
          }
          if (null !== this.maxStayDateAfterCheckIn && this.maxStayDateAfterCheckIn.getTime() < date.getTime()) {
            calendarDateData.title += '\n' + MPHB._data.translations.moreThanMaxDaysStay;
          }
        }
        if (this.isCheckInSelected || this.isCheckOutSelected) {
          var dates = this.$calendarElement.datepick('getDate'),
            checkInSelectedDate = MPHB.Utils.cloneDate(dates[0]),
            checkInFormattedDate = $.datepick.formatDate('yyyy-mm-dd', checkInSelectedDate),
            checkOutSelectedDate = MPHB.Utils.cloneDate(dates[1]),
            checkOutFormattedDate = $.datepick.formatDate('yyyy-mm-dd', checkOutSelectedDate),
            currentProcessingFormattedDate = $.datepick.formatDate('yyyy-mm-dd', date);

          // normalise date to avoide days border fluctuations
          checkInSelectedDate.setHours(0, 0, 0, 0);
          checkOutSelectedDate.setHours(23, 59, 59, 999);
          if (checkInFormattedDate === currentProcessingFormattedDate) {
            calendarDateData.dateClass += ' mphb-selected-date--check-in';
          } else if (this.isCheckOutSelected && checkOutFormattedDate === currentProcessingFormattedDate) {
            calendarDateData.dateClass += ' mphb-selected-date--check-out';
          } else if (this.isCheckInSelected && this.isCheckOutSelected && checkInSelectedDate.getTime() <= date.getTime() && checkOutSelectedDate.getTime() >= date.getTime()) {
            calendarDateData.dateClass += ' mphb-selected-date';
          }
        }
        return calendarDateData;
      },
      selectCheckInDateInCalendar: function selectCheckInDateInCalendar($newCheckInDate) {
        var selectedDates = this.$calendarElement.datepick('getDate');
        if ($.datepick.formatDate('yyyy-mm-dd', selectedDates[0]) !== $.datepick.formatDate('yyyy-mm-dd', $newCheckInDate)) {
          if (this.isCheckInSelected && !this.isCheckOutSelected) {
            // switch to Check-In selection mode
            this.isCheckInSelected = false;
          }
          this.$calendarElement.datepick('setDate', $newCheckInDate);
          var instance = $.datepick._getInst(this.$calendarElement);
          instance.pickingRange = true;
          this.refresh();
        }
      },
      selectCheckOutDateInCalendar: function selectCheckOutDateInCalendar($newCheckOutDate) {
        if (!this.isCheckInSelected) return;
        var selectedDates = this.$calendarElement.datepick('getDate');
        if ($.datepick.formatDate('yyyy-mm-dd', selectedDates[1]) !== $.datepick.formatDate('yyyy-mm-dd', $newCheckOutDate)) {
          if (this.isCheckOutSelected) {
            // switch to Check-Out selection mode
            this.isCheckOutSelected = false;
          }
          this.$calendarElement.datepick('setDate', selectedDates[0], $newCheckOutDate);
          this.refresh();
        }
      },
      initSyncWithReservationForm: function initSyncWithReservationForm() {
        var _this = this;
        // get selected dates from booking form if it has them from session
        var reservationFormCheckInDate = this.$reservationFormCheckInElement.datepick('getDate')[0],
          reservationFormCheckOutDate = this.$reservationFormCheckOutElement.datepick('getDate')[0];
        if (reservationFormCheckInDate) {
          this.isSyncWithReservationFormOn = false;
          this.selectCheckInDateInCalendar(reservationFormCheckInDate);
          if (reservationFormCheckOutDate) {
            this.selectCheckOutDateInCalendar(reservationFormCheckOutDate);
          }
          this.isSyncWithReservationFormOn = true;
        }
        this.$reservationFormCheckInElement.change(function (event) {
          var reservationFormCheckInDate = _this.$reservationFormCheckInElement.datepick('getDate')[0];
          _this.isSyncWithReservationFormOn = false;
          _this.selectCheckInDateInCalendar(reservationFormCheckInDate);
          _this.isSyncWithReservationFormOn = true;
        });
        this.$reservationFormCheckOutElement.change(function (event) {
          var reservationFormCheckOutDate = _this.$reservationFormCheckOutElement.datepick('getDate')[0];

          // we do not clear check-out in calendar because it clears after check-in selected
          if (undefined === reservationFormCheckOutDate) return;
          _this.isSyncWithReservationFormOn = false;
          _this.selectCheckOutDateInCalendar(reservationFormCheckOutDate);
          _this.isSyncWithReservationFormOn = true;
        });
      },
      fillReservationFormWithSelectedDates: function fillReservationFormWithSelectedDates(selectedDates) {
        if (!this.isSyncWithReservationFormOn) return;
        if (this.isCheckInSelected) {
          var reservationFormCheckInDate = this.$reservationFormCheckInElement.datepick('getDate')[0];
          if ($.datepick.formatDate('yyyy-mm-dd', selectedDates[0]) !== $.datepick.formatDate('yyyy-mm-dd', reservationFormCheckInDate)) {
            this.$reservationFormCheckInElement.datepick('setDate', selectedDates[0]);
          }
        }
        if (this.isCheckOutSelected) {
          var reservationFormCheckOutDate = this.$reservationFormCheckOutElement.datepick('getDate')[0];
          if ($.datepick.formatDate('yyyy-mm-dd', selectedDates[1]) !== $.datepick.formatDate('yyyy-mm-dd', reservationFormCheckOutDate)) {
            this.$reservationFormCheckOutElement.datepick('setDate', selectedDates[1]);
          }
        } else {
          // clear check-out date if it was set before
          this.$reservationFormCheckOutElement.datepick('setDate', null);
        }
      },
      refresh: function refresh() {
        this.$calendarElement.hide();
        $.datepick._update(this.$calendarElement, true);
        this.$calendarElement.show();
      }
    });

    /**
     *
     * @requires ./../datepicker.js
     */
    MPHB.Datepicker('MPHB.RoomTypeCheckInDatepicker', {}, {
      getDatepickSettings: function getDatepickSettings() {
        var self = this;
        return {
          defaultDate: this.firstAvailableCheckInDate,
          onShow: function onShow(element, instance) {
            MPHB.restApiHelper.getAvailabilityData({
              // We load a day before and day after for availability calculations later
              start_date: new Date(instance.drawDate.getFullYear(), instance.drawDate.getMonth(), 0),
              end_date: new Date(instance.drawDate.getFullYear(), instance.drawDate.getMonth() + MPHB._data.settings.numberOfMonthDatepicker, 1),
              room_type_ids: [self.roomTypeId]
            }, function () {
              self.lock();
            }, function () {
              self.form.updateCheckOutLimitations();
              self.refresh();
              self.unlock();
            });

            // 	remove highlight right after calendar was shown
            // 	to avoide of date highlighting when date is not selected yet
            element.find('.datepick-highlight').removeClass('datepick-highlight');
          },
          onChangeMonthYear: function onChangeMonthYear(year, month) {
            MPHB.restApiHelper.getAvailabilityData({
              // We load a day before and day after for availability calculations later
              start_date: new Date(year, month - 1, 0),
              end_date: new Date(year, month - 1 + MPHB._data.settings.numberOfMonthDatepicker, 1),
              room_type_ids: [self.roomTypeId]
            }, function () {
              self.lock();
            }, function () {
              self.refresh();
              self.unlock();
            });
          },
          onSelect: function onSelect(dates) {
            self.form.updateCheckOutLimitations();
            self.form.onDatepickChange();

            // we clear check-out date if a new check-in date was selected
            self.form.checkOutDatepicker.clear();
            self.element.trigger('change');
          },
          onDate: function onDate(date, isCurrentMonth) {
            var _roomTypeCalendarData5;
            var roomTypeCalendarData = MPHB.restApiHelper.getCachedAvailabilityData([self.roomTypeId]);
            var calendarDateAttributes = MPHB.calendarHelper.getCalendarDateAttributesFromAvailability(2, date, isCurrentMonth, (_roomTypeCalendarData5 = roomTypeCalendarData[self.roomTypeId]) !== null && _roomTypeCalendarData5 !== void 0 ? _roomTypeCalendarData5 : {});
            return calendarDateAttributes;
          },
          pickerClass: 'mphb-datepick-popup mphb-check-in-datepick ' + MPHB._data.settings.datepickerClass
        };
      }
    });

    /**
     *
     * @requires ./../datepicker.js
     */
    MPHB.RoomTypeCheckOutDatepicker = MPHB.Datepicker.extend({}, {
      minCheckOutDateForSelection: null,
      maxCheckOutDateForSelection: null,
      minStayDateAfterCheckIn: null,
      maxStayDateAfterCheckIn: null,
      /**
       * @param {Date} checkInDate
       */
      updateCheckOutLimitations: function updateCheckOutLimitations(checkInDate) {
        var _roomTypeCalendarData6;
        if (!checkInDate) {
          this.minCheckOutDateForSelection = null;
          this.maxCheckOutDateForSelection = null;
          this.minStayDateAfterCheckIn = null;
          this.maxStayDateAfterCheckIn = null;
          this.element.datepick('option', 'defaultDate', this.firstAvailableCheckInDate);
          return;
        }
        var roomTypeCalendarData = MPHB.restApiHelper.getCachedAvailabilityData([this.roomTypeId]);
        var result = MPHB.calendarHelper.calculateMinMaxCheckOutDateForSelection(checkInDate, (_roomTypeCalendarData6 = roomTypeCalendarData[this.roomTypeId]) !== null && _roomTypeCalendarData6 !== void 0 ? _roomTypeCalendarData6 : {});
        this.minCheckOutDateForSelection = result.minCheckOutDateForSelection;
        this.maxCheckOutDateForSelection = result.maxCheckOutDateForSelection;
        this.minStayDateAfterCheckIn = result.minStayDateAfterCheckIn;
        this.maxStayDateAfterCheckIn = result.maxStayDateAfterCheckIn;
        this.element.datepick('option', 'defaultDate', this.minCheckOutDateForSelection ? this.minCheckOutDateForSelection : checkInDate);
      },
      getDatepickSettings: function getDatepickSettings() {
        var self = this;
        return {
          defaultDate: this.minCheckOutDateForSelection ? this.minCheckOutDateForSelection : this.firstAvailableCheckInDate,
          onShow: function onShow(element, instance) {
            MPHB.restApiHelper.getAvailabilityData({
              // We load a day before and day after for availability calculations later
              start_date: new Date(instance.drawDate.getFullYear(), instance.drawDate.getMonth(), 0),
              end_date: new Date(instance.drawDate.getFullYear(), instance.drawDate.getMonth() + MPHB._data.settings.numberOfMonthDatepicker, 1),
              room_type_ids: [self.roomTypeId]
            }, function () {
              self.lock();
            }, function () {
              self.form.updateCheckOutLimitations();
              self.refresh();
              self.unlock();
            });

            // 	remove highlight right after calendar was shown
            // 	to avoide of date highlighting when date is not selected yet
            element.find('.datepick-highlight').removeClass('datepick-highlight');
          },
          onChangeMonthYear: function onChangeMonthYear(year, month) {
            MPHB.restApiHelper.getAvailabilityData({
              // We load a day before and day after for availability calculations later
              start_date: new Date(year, month - 1, 0),
              end_date: new Date(year, month - 1 + MPHB._data.settings.numberOfMonthDatepicker, 1),
              room_type_ids: [self.roomTypeId]
            }, function () {
              self.lock();
            }, function () {
              self.form.updateCheckOutLimitations();
              self.refresh();
              self.unlock();
            });
          },
          onSelect: function onSelect(dates) {
            self.form.onDatepickChange();
            self.element.trigger('change');
          },
          onDate: function onDate(date, isCurrentMonth) {
            var _roomTypeCalendarData7;
            var roomTypeCalendarData = MPHB.restApiHelper.getCachedAvailabilityData([self.roomTypeId]);
            var calendarDateAttributes = MPHB.calendarHelper.getCalendarDateAttributesFromAvailability(3, date, isCurrentMonth, (_roomTypeCalendarData7 = roomTypeCalendarData[self.roomTypeId]) !== null && _roomTypeCalendarData7 !== void 0 ? _roomTypeCalendarData7 : {}, false, self.form.checkInDatepicker.getDate(), self.minStayDateAfterCheckIn, self.maxStayDateAfterCheckIn, self.minCheckOutDateForSelection, self.maxCheckOutDateForSelection);
            return calendarDateAttributes;
          },
          pickerClass: 'mphb-datepick-popup mphb-check-out-datepick ' + MPHB._data.settings.datepickerClass
        };
      }
    });

    /**
     *
     * @requires ./../datepicker.js
     */
    MPHB.SearchCheckInDatepicker = MPHB.Datepicker.extend({}, {
      getDatepickSettings: function getDatepickSettings() {
        var self = this;
        return {
          defaultDate: this.firstAvailableCheckInDate,
          onShow: function onShow(element, instance) {
            MPHB.restApiHelper.getAvailabilityData({
              // We load a day before and day after for availability calculations later
              start_date: new Date(instance.drawDate.getFullYear(), instance.drawDate.getMonth(), 0),
              end_date: new Date(instance.drawDate.getFullYear(), instance.drawDate.getMonth() + MPHB._data.settings.numberOfMonthDatepicker, 1)
            }, function () {
              self.lock();
            }, function () {
              self.form.updateCheckOutLimitations();
              self.refresh();
              self.unlock();
            });

            // 	remove highlight right after calendar was shown
            // 	to avoide of date highlighting when date is not selected yet
            element.find('.datepick-highlight').removeClass('datepick-highlight');
          },
          onChangeMonthYear: function onChangeMonthYear(year, month) {
            MPHB.restApiHelper.getAvailabilityData({
              // We load a day before and day after for availability calculations later
              start_date: new Date(year, month - 1, 0),
              end_date: new Date(year, month - 1 + MPHB._data.settings.numberOfMonthDatepicker, 1)
            }, function () {
              self.lock();
            }, function () {
              self.refresh();
              self.unlock();
            });
          },
          onSelect: function onSelect(dates) {
            self.form.updateCheckOutLimitations();
          },
          onDate: function onDate(date, isCurrentMonth) {
            var _roomTypeCalendarData8;
            var roomTypeCalendarData = MPHB.restApiHelper.getCachedAvailabilityData();
            var calendarDateAttributes = MPHB.calendarHelper.getCalendarDateAttributesFromAvailability(2, date, isCurrentMonth, (_roomTypeCalendarData8 = roomTypeCalendarData[0]) !== null && _roomTypeCalendarData8 !== void 0 ? _roomTypeCalendarData8 : {});
            return calendarDateAttributes;
          },
          pickerClass: 'mphb-datepick-popup mphb-check-in-datepick ' + MPHB._data.settings.datepickerClass
        };
      }
    });

    /**
     *
     * @requires ./../datepicker.js
     */
    MPHB.SearchCheckOutDatepicker = MPHB.Datepicker.extend({}, {
      minCheckOutDateForSelection: null,
      maxCheckOutDateForSelection: null,
      minStayDateAfterCheckIn: null,
      maxStayDateAfterCheckIn: null,
      /**
       * @param {Date} checkInDate
       */
      updateCheckOutLimitations: function updateCheckOutLimitations(checkInDate) {
        var _roomTypeCalendarData9;
        if (!checkInDate) {
          this.minCheckOutDateForSelection = null;
          this.maxCheckOutDateForSelection = null;
          this.minStayDateAfterCheckIn = null;
          this.maxStayDateAfterCheckIn = null;
          return;
        }
        var roomTypeCalendarData = MPHB.restApiHelper.getCachedAvailabilityData();
        var result = MPHB.calendarHelper.calculateMinMaxCheckOutDateForSelection(checkInDate, (_roomTypeCalendarData9 = roomTypeCalendarData[0]) !== null && _roomTypeCalendarData9 !== void 0 ? _roomTypeCalendarData9 : {});
        this.minCheckOutDateForSelection = result.minCheckOutDateForSelection;
        this.maxCheckOutDateForSelection = result.maxCheckOutDateForSelection;
        this.minStayDateAfterCheckIn = result.minStayDateAfterCheckIn;
        this.maxStayDateAfterCheckIn = result.maxStayDateAfterCheckIn;
        if (!this.getDate() || this.getDate() <= checkInDate) {
          this.setDate(this.minCheckOutDateForSelection);
        }
      },
      getDatepickSettings: function getDatepickSettings() {
        var self = this;
        return {
          defaultDate: this.firstAvailableCheckInDate,
          onShow: function onShow(element, instance) {
            MPHB.restApiHelper.getAvailabilityData({
              // We load a day before and day after for availability calculations later
              start_date: new Date(instance.drawDate.getFullYear(), instance.drawDate.getMonth(), 0),
              end_date: new Date(instance.drawDate.getFullYear(), instance.drawDate.getMonth() + MPHB._data.settings.numberOfMonthDatepicker, 1)
            }, function () {
              self.lock();
            }, function () {
              self.form.updateCheckOutLimitations();
              self.refresh();
              self.unlock();
            });

            // 	remove highlight right after calendar was shown
            // 	to avoide of date highlighting when date is not selected yet
            element.find('.datepick-highlight').removeClass('datepick-highlight');
          },
          onChangeMonthYear: function onChangeMonthYear(year, month) {
            var _self$form$getCheckIn;
            var instance = $.datepick._getInst(self.element[0]);
            var calendarDrawDate = new Date(instance.drawDate.getTime());
            var selectedCheckInDateorToday = (_self$form$getCheckIn = self.form.getCheckInDate()) !== null && _self$form$getCheckIn !== void 0 ? _self$form$getCheckIn : new Date();
            MPHB.restApiHelper.getAvailabilityData({
              // We load data from selected check-in date because we need it for updateCheckOutLimitations()
              start_date: new Date(selectedCheckInDateorToday.getFullYear(), selectedCheckInDateorToday.getMonth(), 0),
              end_date: new Date(year, month - 1 + MPHB._data.settings.numberOfMonthDatepicker, 1)
            }, function () {
              self.lock();
            }, function () {
              self.form.updateCheckOutLimitations();
              var instance = $.datepick._getInst(self.element[0]);
              // we need to set draw date because it could be changed
              // after updateCheckOutLimitations() when we set calendar date
              instance.drawDate = calendarDrawDate;
              self.refresh();
              self.unlock();
            });
          },
          onDate: function onDate(date, isCurrentMonth) {
            var _roomTypeCalendarData0;
            var roomTypeCalendarData = MPHB.restApiHelper.getCachedAvailabilityData();
            var calendarDateAttributes = MPHB.calendarHelper.getCalendarDateAttributesFromAvailability(3, date, isCurrentMonth, (_roomTypeCalendarData0 = roomTypeCalendarData[0]) !== null && _roomTypeCalendarData0 !== void 0 ? _roomTypeCalendarData0 : {}, false, self.form.checkInDatepicker.getDate(), self.minStayDateAfterCheckIn, self.maxStayDateAfterCheckIn, self.minCheckOutDateForSelection, self.maxCheckOutDateForSelection);
            return calendarDateAttributes;
          },
          pickerClass: 'mphb-datepick-popup mphb-check-out-datepick ' + MPHB._data.settings.datepickerClass
        };
      }
    });
    MPHB.SearchForm = can.Control.extend({}, {
      checkInDatepicker: null,
      checkOutDatepicker: null,
      init: function init($formElement) {
        var firstAvailableCheckInDateYmd = $formElement.attr('data-first_available_check_in_date');
        if (!firstAvailableCheckInDateYmd) {
          firstAvailableCheckInDateYmd = $.datepick.formatDate('yyyy-mm-dd', new Date());
        }
        this.checkInDatepicker = new MPHB.SearchCheckInDatepicker($formElement.find('.mphb-datepick[id^="mphb_check_in_date"]'), {
          form: this,
          roomTypeId: 0,
          firstAvailableCheckInDateYmd: firstAvailableCheckInDateYmd
        });
        this.checkOutDatepicker = new MPHB.SearchCheckOutDatepicker($formElement.find('.mphb-datepick[id^="mphb_check_out_date"]'), {
          form: this,
          roomTypeId: 0,
          firstAvailableCheckInDateYmd: firstAvailableCheckInDateYmd
        });
      },
      getCheckInDate: function getCheckInDate() {
        return this.checkInDatepicker.getDate() ? MPHB.Utils.cloneDate(this.checkInDatepicker.getDate()) : null;
      },
      updateCheckOutLimitations: function updateCheckOutLimitations() {
        this.checkOutDatepicker.updateCheckOutLimitations(this.checkInDatepicker.getDate());
      }
    });
    MPHB.RoomBookSection = can.Control.extend({}, {
      roomTypeId: null,
      roomTitle: '',
      roomPrice: 0,
      quantitySelect: null,
      bookButton: null,
      confirmButton: null,
      removeButton: null,
      messageHolder: null,
      messageWrapper: null,
      form: null,
      init: function init(el, args) {
        this.reservationCart = args.reservationCart;
        this.roomTypeId = parseInt(el.attr('data-room-type-id'));
        this.roomTitle = el.attr('data-room-type-title');
        this.roomPrice = parseFloat(el.attr('data-room-price'));
        this.confirmButton = el.find('.mphb-confirm-reservation');
        this.quantitySelect = el.find('.mphb-rooms-quantity');
        this.messageWrapper = el.find('.mphb-rooms-reservation-message-wrapper');
        this.messageHolder = el.find('.mphb-rooms-reservation-message');
      },
      /**
       *
       * @returns {int}
       */
      getRoomTypeId: function getRoomTypeId() {
        return this.roomTypeId;
      },
      /**
       *
       * @returns {Number}
       */
      getPrice: function getPrice() {
        return this.roomPrice;
      },
      '.mphb-book-button click': function mphbBookButton_click(button, e) {
        e.preventDefault();
        e.stopPropagation();
        var quantity = this.quantitySelect.length ? parseInt(this.quantitySelect.val()) : 1;
        this.reservationCart.addToCart(this.roomTypeId, quantity);
        if (!MPHB._data.settings.isDirectBooking) {
          // Add message "N x ... has/have been added to your reservation."
          var messagePattern = 1 == quantity ? MPHB._data.translations.roomsAddedToReservation_singular : MPHB._data.translations.roomsAddedToReservation_plural;
          var message = messagePattern.replace('%1$d', quantity).replace('%2$s', this.roomTitle);
          this.messageHolder.html(message);

          // Show "N x ... has/have been added to your reservation." message
          // Show "Remove" button
          // Show "Confirm Reservation" button
          this.element.addClass('mphb-rooms-added');
        } else {
          button.prop('disabled', true);

          // Go to the Checkout immediately
          this.reservationCart.confirmReservation();
        }
      },
      '.mphb-remove-from-reservation click': function mphbRemoveFromReservation_click(el, e) {
        e.preventDefault();
        e.stopPropagation();
        this.reservationCart.removeFromCart(this.roomTypeId);
        this.messageHolder.empty();
        this.element.removeClass('mphb-rooms-added');
      },
      '.mphb-confirm-reservation click': function mphbConfirmReservation_click(el, e) {
        e.preventDefault();
        e.stopPropagation();
        this.reservationCart.confirmReservation();
      }
    });

    /**
     *
     * @requires ./room-book-section.js
     */
    MPHB.ReservationCart = can.Control.extend({}, {
      cartForm: null,
      cartDetails: null,
      roomBookSections: {},
      cartContents: {},
      init: function init(el, args) {
        this.cartForm = el.find('#mphb-reservation-cart');
        this.cartDetails = el.find('.mphb-reservation-details');
        this.initRoomBookSections(el.find('.mphb-reserve-room-section'));
      },
      initRoomBookSections: function initRoomBookSections(sections) {
        var self = this;
        var bookSection;
        $.each(sections, function (index, roomSection) {
          bookSection = new MPHB.RoomBookSection($(roomSection), {
            reservationCart: self
          });
          self.roomBookSections[bookSection.getRoomTypeId()] = bookSection;
        });
      },
      addToCart: function addToCart(roomTypeId, quantity) {
        this.cartContents[roomTypeId] = quantity;
        this.updateCartView();
        this.updateCartInputs();
      },
      removeFromCart: function removeFromCart(roomTypeId) {
        delete this.cartContents[roomTypeId];
        this.updateCartView();
        this.updateCartInputs();
      },
      calcRoomsInCart: function calcRoomsInCart() {
        var count = 0;
        $.each(this.cartContents, function (roomTypeId, quantity) {
          count += quantity;
        });
        return count;
      },
      calcTotalPrice: function calcTotalPrice() {
        var total = 0;
        var price = 0;
        var self = this;
        $.each(this.cartContents, function (roomTypeId, quantity) {
          price = self.roomBookSections[roomTypeId].getPrice();
          total += price * quantity;
        });
        return total;
      },
      updateCartView: function updateCartView() {
        if (!$.isEmptyObject(this.cartContents)) {
          var roomsCount = this.calcRoomsInCart();
          var messageTemplate = 1 == roomsCount ? MPHB._data.translations.countRoomsSelected_singular : MPHB._data.translations.countRoomsSelected_plural;
          var cartMessage = messageTemplate.replace('%s', roomsCount);
          this.cartDetails.find('.mphb-cart-message').html(cartMessage);
          var total = this.calcTotalPrice();
          var totalMessage = MPHB.format_price(total, {
            'trim_zeros': true
          });
          this.cartDetails.find('.mphb-cart-total-price>.mphb-cart-total-price-value').html(totalMessage);
          this.cartForm.removeClass('mphb-empty-cart');
        } else {
          this.cartForm.addClass('mphb-empty-cart');
        }
      },
      updateCartInputs: function updateCartInputs() {
        // empty inputs
        this.cartForm.find('[name^="mphb_rooms_details"]').remove();
        var self = this;
        $.each(this.cartContents, function (roomTypeId, quantity) {
          var input = $('<input />', {
            name: 'mphb_rooms_details[' + roomTypeId + ']',
            type: 'hidden',
            value: quantity
          });
          self.cartForm.prepend(input);
        });
      },
      confirmReservation: function confirmReservation() {
        this.cartForm.submit();
      }
    });

    /**
     * @requires ../stripe-gateway.js
     *
     * @since 3.6.0
     */
    MPHB.StripeGateway.PaymentMethods = can.Construct.extend({}, {
      listAll: ['card', 'bancontact', 'ideal', 'giropay', 'sepa_debit', 'klarna'],
      klarnaAllowedCountryCodes: ['AT', 'AU', 'BE', 'CA', 'CH', 'CZ', 'DE', 'DK', 'ES', 'FI', 'FR', 'GB', 'GR', 'IE', 'IT', 'NL', 'NO', 'NZ', 'PL', 'PT', 'RO', 'SE', 'US'],
      listEnabled: ['card'],
      paymentMethods: {},
      currentPayment: 'card',
      currentCountry: '',
      currentCurrencyCode: '',
      inputs: null,
      // input[name="stripe_payment_method"] elements

      isMounted: false,
      init: function init(enabledPayments, defaultCountry, currentCurrencyCode) {
        this.listEnabled = enabledPayments.slice(0); // Clone array
        this.currentCurrencyCode = currentCurrencyCode;
        this.initPayments();

        // Change the country only when paymentMethods data are fully ready
        this.selectCountry(defaultCountry);
      },
      initPayments: function initPayments() {
        var self = this;
        this.forEach(function (payment) {
          self.paymentMethods[payment] = {
            isEnabled: self.listEnabled.indexOf(payment) >= 0,
            nav: null,
            // .mphb-stripe-payment-method.%payment% element
            fields: null // .mphb-stripe-payment-fields.%payment% element
          };
        });
      },
      selectPayment: function selectPayment(payment) {
        if (payment == this.currentPayment || !this.paymentMethods.hasOwnProperty(payment)) {
          return;
        }
        this.togglePayment(this.currentPayment, false);
        this.togglePayment(payment, true);
        this.currentPayment = payment;
      },
      togglePayment: function togglePayment(payment, enable) {
        if (this.isMounted) {
          this.paymentMethods[payment].nav.toggleClass('active', enable);
          this.paymentMethods[payment].fields.toggleClass('mphb-hide', !enable);
        }
      },
      selectCountry: function selectCountry(country) {
        if (country === this.currentCountry) {
          return;
        }
        this.currentCountry = country;

        // Reset selected payment method
        this.selectPayment('card');
        this.showRelevantMethods();
      },
      showRelevantMethods: function showRelevantMethods() {
        if (!this.isMounted) {
          return;
        }
        var self = this;
        this.forEach(function (payment, paymentMethod) {
          var isPaymentMethodEnabled = paymentMethod.isEnabled;
          if ('klarna' === payment) {
            isPaymentMethodEnabled = -1 !== self.klarnaAllowedCountryCodes.indexOf(self.currentCountry);
            if ('GB' === self.currentCountry && 'GBP' !== self.currentCurrencyCode) {
              isPaymentMethodEnabled = false;
            }
          }

          // hide not enabled method nav
          paymentMethod.nav.toggleClass('mphb-hide', !isPaymentMethodEnabled);

          // Show only fields of the selected payment method
          paymentMethod.fields.toggleClass('mphb-hide', payment != self.currentPayment);
        });

        // Select proper radio button
        this.inputs.val([this.currentPayment]);
      },
      mount: function mount(section) {
        this.forEach(function (payment, paymentMethod) {
          paymentMethod.nav = section.find('.mphb-stripe-payment-method.' + payment);
          paymentMethod.fields = section.find('.mphb-stripe-payment-fields.' + payment);
        });
        this.inputs = section.find('input[name="stripe_payment_method"]');
        this.isMounted = true;
        this.showRelevantMethods();
      },
      unmount: function unmount() {
        this.forEach(function (_, paymentMethod) {
          paymentMethod.nav = null;
          paymentMethod.fields = null;
        });
        this.inputs = null;
        this.isMounted = false;
      },
      forEach: function forEach(callback) {
        var self = this;
        this.listAll.forEach(function (payment) {
          // callback(string, Object, MPHB.StripeGateway.PaymentMethods)
          callback(payment, self.paymentMethods[payment], self);
        });
      },
      isEnabled: function isEnabled(payment) {
        return this.paymentMethods[payment].isEnabled;
      },
      onlyCardEnabled: function onlyCardEnabled() {
        return this.listEnabled.length == 1 && this.paymentMethods.card.isEnabled;
      },
      isSelected: function isSelected(payment) {
        return payment == this.currentPayment;
      }
    });
    if (MPHB._data.page.isCheckoutPage) {
      new MPHB.CheckoutForm($('.mphb_sc_checkout-form'));
    } else if (MPHB._data.page.isCreateBookingPage) {
      new MPHB.AdminCheckoutForm($('.mphb_cb_checkout_form'));
    }
    if (MPHB._data.page.isSearchResultsPage) {
      new MPHB.ReservationCart($('.mphb_sc_search_results-wrapper'));
    }
    var calendars = $('.mphb-calendar.mphb-datepick');
    $.each(calendars, function (index, calendarEl) {
      new MPHB.RoomTypeCalendar($(calendarEl));
    });
    var reservationForms = $('.mphb-booking-form');
    $.each(reservationForms, function (index, formEl) {
      new MPHB.ReservationForm($(formEl));
    });
    var searchForms = $('form.mphb_sc_search-form, form.mphb_widget_search-form, form.mphb_cb_search_form');
    $.each(searchForms, function (index, formEl) {
      new MPHB.SearchForm($(formEl));
    });
    var flexsliderGalleries = $('.mphb-flexslider-gallery-wrapper');
    $.each(flexsliderGalleries, function (index, flexsliderGallery) {
      new MPHB.FlexsliderGallery(flexsliderGallery);
    });
    var termsAndConditions = $('.mphb-checkout-terms-wrapper');
    if (termsAndConditions.length > 0) {
      new MPHB.TermsSwitcher(termsAndConditions);
    }

    // Fix for kbwood/datepick (function show() -> $.ui.version.substring(2))
    if ($.ui == undefined) {
      $.ui = {};
    }
    if ($.ui.version == undefined) {
      $.ui.version = '1.5-';
    }
  });
})(jQuery);