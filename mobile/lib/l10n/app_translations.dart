import 'package:get/get.dart';

class AppTranslations {
  // Dialogs
  static String get loading => 'dialogs.loading'.tr;
  static String get error => 'dialogs.error'.tr;
  static String get success => 'dialogs.success'.tr;

  // General
  static String get yes => 'general.yes'.tr;
  static String get no => 'general.no'.tr;
  static String get select => 'general.select'.tr;
  static String get search => 'general.search'.tr;
  static String get noItems => 'general.noItems'.tr;
  static String get edit => 'general.edit'.tr;
  static String get cancel => 'general.cancel'.tr;
  static String get submit => 'general.submit'.tr;
  static String get confirm => 'general.confirm'.tr;
  static String get discoverAll => 'general.discoverAll'.tr;

  // Validation
  static String get requiredField => 'validation.requiredField'.tr;
  static String get invalidEmail => 'validation.invalidEmail'.tr;
  static String get numberField => 'validation.numberField'.tr;
  static String get invalidOtp => 'validation.invalidOtp'.tr;
  static String get invalidNumber => 'validation.invalidNumber'.tr;
  static String get invalidPasswordLength =>
      'validation.invalidPasswordLength'.tr;
  static String get invalidPasswordChar => 'validation.invalidPasswordChar'.tr;
  static String get invalidPasswordNumber =>
      'validation.invalidPasswordNumber'.tr;
  static String get invalidConfirmPassword => 'validation.confirmPassword'.tr;

  // API Service
  static String get noInternetConnection => 'api.noInternetConnection'.tr;
  static String get checkInternetConnection => 'api.checkInternetConnection'.tr;
  static String get unknownError => 'api.unknownError'.tr;
  static String get requestTimeout => 'api.requestTimeout'.tr;
  static String get forbiddenRequest => 'api.forbiddenRequest'.tr;
  static String get resourceNotFound => 'api.resourceNotFound'.tr;
  static String get tooManyRequests => 'api.tooManyRequests'.tr;
  static String get serviceUnavailable => 'api.serviceUnavailable'.tr;
  static String get serverError => 'api.serverError'.tr;
  static String get downloading => 'api.downloading'.tr;
  static String get uploading => 'api.uploading'.tr;

  // Auth
  static String get phoneNumber => 'auth.phoneNumber'.tr;
  static String get sessionExpired => 'auth.sessionExpired'.tr;
  static String get pleaseLoginAgain => 'auth.pleaseLoginAgain'.tr;
  static String get confirmPassword => 'auth.confirmPassword'.tr;
  static String get email => 'auth.email'.tr;
  static String get support => 'auth.support'.tr;

  // Auth — Carlton flow
  static String get welcomeBackTitle => 'auth.welcomeBackTitle'.tr;
  static String get welcomeBackSubtitle => 'auth.welcomeBackSubtitle'.tr;
  static String get signInTitle => 'auth.signInTitle'.tr;
  static String get signInSubtitle => 'auth.signInSubtitle'.tr;
  static String get signInByPhoneTab => 'auth.signInByPhoneTab'.tr;
  static String get signInByEmailTab => 'auth.signInByEmailTab'.tr;
  static String get emailAddressLabel => 'auth.emailAddressLabel'.tr;
  static String get emailAddressHint => 'auth.emailAddressHint'.tr;
  static String get nextButtonLabel => 'auth.nextButtonLabel'.tr;
  static String get newGuestPrompt => 'auth.newGuestPrompt'.tr;
  static String get createAccountLink => 'auth.createAccountLink'.tr;
  static String get addPhoneTitle => 'auth.addPhoneTitle'.tr;
  static String get addPhoneSubtitle => 'auth.addPhoneSubtitle'.tr;
  static String get sendCodeButtonLabel => 'auth.sendCodeButtonLabel'.tr;
  static String get verifyIdentityTitle => 'auth.verifyIdentityTitle'.tr;
  static String otpSentTo(String destination) =>
      'auth.otpSentTo'.trParams({'destination': destination});
  static String resendIn(String time) =>
      'auth.resendIn'.trParams({'time': time});
  static String get resendCodeLink => 'auth.resendCodeLink'.tr;
  static String get verifyButtonLabel => 'auth.verifyButtonLabel'.tr;
  static String get createProfileTitle => 'auth.createProfileTitle'.tr;
  static String get createProfileSubtitle => 'auth.createProfileSubtitle'.tr;
  static String get firstNameLabel => 'auth.firstNameLabel'.tr;
  static String get firstNameHint => 'auth.firstNameHint'.tr;
  static String get lastNameLabel => 'auth.lastNameLabel'.tr;
  static String get lastNameHint => 'auth.lastNameHint'.tr;
  static String get continueButtonLabel => 'auth.continueButtonLabel'.tr;
  static String get pleaseEnterPhoneNumber => 'auth.pleaseEnterPhoneNumber'.tr;
  static String get pleaseEnterEmailAddress =>
      'auth.pleaseEnterEmailAddress'.tr;
  static String get phoneNumberHint => 'auth.phoneNumberHint'.tr;

  // Navigation
  static String get navHome => 'nav.home'.tr;
  static String get navStays => 'nav.stays'.tr;
  static String get navBook => 'nav.book'.tr;
  static String get navServices => 'nav.services'.tr;
  static String get navAccount => 'nav.account'.tr;

  // Home
  static String get roomsSuites => 'home.roomsSuites'.tr;
  static String get diningRestaurants => 'home.diningRestaurants'.tr;
  static String get experiences => 'home.experiences'.tr;
  static String get menuComingSoon => 'home.menuComingSoon'.tr;
  static String sectionComingSoon(String section) =>
      'home.sectionComingSoon'.trParams({'section': section});
  static String get heroLocation => 'home.heroLocation'.tr;
  static String get heroVideoTitlePre => 'home.heroVideoTitlePre'.tr;
  static String get heroVideoTitleItalic => 'home.heroVideoTitleItalic'.tr;
  static String get heroVideoTitlePost => 'home.heroVideoTitlePost'.tr;
  static String get heroVideoSubtitle => 'home.heroVideoSubtitle'.tr;
  static String get heroDiningTitlePre => 'home.heroDiningTitlePre'.tr;
  static String get heroDiningTitleItalic => 'home.heroDiningTitleItalic'.tr;
  static String get heroDiningTitlePost => 'home.heroDiningTitlePost'.tr;
  static String get heroDiningSubtitle => 'home.heroDiningSubtitle'.tr;
  static String get heroExperiencesTitlePre =>
      'home.heroExperiencesTitlePre'.tr;
  static String get heroExperiencesTitleItalic =>
      'home.heroExperiencesTitleItalic'.tr;
  static String get heroExperiencesTitlePost =>
      'home.heroExperiencesTitlePost'.tr;
  static String get heroExperiencesSubtitle =>
      'home.heroExperiencesSubtitle'.tr;
  static String get bookNowLabel => 'home.bookNowLabel'.tr;
  static String get exploreLabel => 'home.exploreLabel'.tr;
  static String get priceFromPrefix => 'home.priceFromPrefix'.tr;
  static String get priceNightSuffix => 'home.priceNightSuffix'.tr;

  // Services
  static String get signInPromptTitle => 'services.signInPromptTitle'.tr;
  static String get accountSignInPromptTitle => 'account.signInPromptTitle'.tr;
  static String get deleteAccount => 'account.deleteAccount'.tr;
  static String get deleteAccountTitle => 'account.deleteTitle'.tr;
  static String get deleteAccountBody => 'account.deleteBody'.tr;
  static String get deleteAccountConfirm => 'account.deleteConfirm'.tr;
  static String get deleteForfeitUnknown => 'account.deleteForfeitUnknown'.tr;
  static String deleteForfeitPoints(String points) =>
      'account.deleteForfeitPoints'.trParams({'points': points});
  static String deleteForfeitVouchers(String count) =>
      'account.deleteForfeitVouchers'.trParams({'count': count});
  static String deleteForfeitBoth({
    required String points,
    required String count,
  }) =>
      'account.deleteForfeitBoth'.trParams({'points': points, 'count': count});
  static String get deleteBlockedTitle => 'account.deleteBlockedTitle'.tr;
  static String get deleteBlockedBody => 'account.deleteBlockedBody'.tr;
  static String deleteBlockedBookings(String codes) =>
      'account.deleteBlockedBookings'.trParams({'codes': codes});
  static String get deleteReasonReservation =>
      'account.deleteReasonReservation'.tr;
  static String get deleteReasonFolio => 'account.deleteReasonFolio'.tr;
  static String get deleteReasonService => 'account.deleteReasonService'.tr;
  static String get accountDeleted => 'account.deleted'.tr;
  static String get staysSignInPromptTitle => 'stays.signInPromptTitle'.tr;
  static String get signInButtonLabel => 'services.signInButtonLabel'.tr;
  static String get readyForNextStayTitle =>
      'services.readyForNextStayTitle'.tr;
  static String get exploreAndBookButtonLabel =>
      'services.exploreAndBookButtonLabel'.tr;
  static String get allServicesTab => 'services.allServicesTab'.tr;
  static String get noActiveRequestsTitle =>
      'services.noActiveRequestsTitle'.tr;
  static String get noActiveRequestsSubtitle =>
      'services.noActiveRequestsSubtitle'.tr;
  static String get browseServicesButtonLabel =>
      'services.browseServicesButtonLabel'.tr;
  static String get checkedInSincePrefix => 'services.checkedInSincePrefix'.tr;
  static String get nightsRemainingPrefix =>
      'services.nightsRemainingPrefix'.tr;

  // Services — In-stay requests (Phase 5)
  static String get serviceComingSoon => 'services.comingSoon'.tr;
  static String etaMinutes(int minutes) =>
      'services.etaMinutes'.trParams({'count': '$minutes'});
  static String get requestSubmitted => 'services.requestSubmitted'.tr;
  static String get requestNeedsCheckIn => 'services.requestNeedsCheckIn'.tr;
  static String get requestFailed => 'services.requestFailed'.tr;
  static String get editComingSoon => 'services.editComingSoon'.tr;
  static String get dndTitle => 'services.dndTitle'.tr;
  static String get dndSubtitle => 'services.dndSubtitle'.tr;
  static String get dndSwitchLabel => 'services.dndSwitchLabel'.tr;
  static String get dndNeedsCheckIn => 'services.dndNeedsCheckIn'.tr;
  static String get dndFailed => 'services.dndFailed'.tr;

  // Dining — table reservations (Phase 5)
  static String tableReservedFor(String label) =>
      'dining.tableReservedFor'.trParams({'label': label});
  static String get tableNeedsBooking => 'dining.tableNeedsBooking'.tr;
  static String get tablePickTime => 'dining.tablePickTime'.tr;
  static String get hotelName => 'general.hotelName'.tr;
  static String get guestRelations => 'chat.guestRelations'.tr;
  static String get callUnavailable => 'chat.callUnavailable'.tr;
  static String get quickReplyHousekeeping => 'chat.quickReplyHousekeeping'.tr;
  static String get quickReplyLateCheckout => 'chat.quickReplyLateCheckout'.tr;
  static String get quickReplyDiningAdvice => 'chat.quickReplyDiningAdvice'.tr;
  static String get quickReplyAirportTransfer =>
      'chat.quickReplyAirportTransfer'.tr;
  static String get tableNoAvailability => 'dining.tableNoAvailability'.tr;
  static String get tableVenueUnavailable => 'dining.tableVenueUnavailable'.tr;
  static String get tableFailed => 'dining.tableFailed'.tr;

  // Pre-arrival e-check-in documents (Phase 5)
  static String get preArrivalTitle => 'preArrival.title'.tr;
  static String get preArrivalIntro => 'preArrival.intro'.tr;
  static String get addDocument => 'preArrival.addDocument'.tr;
  static String get submitDocuments => 'preArrival.submit'.tr;
  static String get preArrivalEmptyTitle => 'preArrival.emptyTitle'.tr;
  static String get preArrivalEmptySubtitle => 'preArrival.emptySubtitle'.tr;
  static String get documentsSubmitted => 'preArrival.submitted'.tr;
  static String get documentsNeedBooking => 'preArrival.needBooking'.tr;
  static String get documentsInvalid => 'preArrival.invalid'.tr;
  static String get documentsFailed => 'preArrival.failed'.tr;
  static String documentType(String type) {
    switch (type) {
      case 'passport':
        return 'preArrival.typePassport'.tr;
      case 'id_card':
        return 'preArrival.typeIdCard'.tr;
      case 'visa':
        return 'preArrival.typeVisa'.tr;
      default:
        return type;
    }
  }

  // AI Concierge
  static String get aiTabLabel => 'concierge.aiTabLabel'.tr;
  static String get customerServiceTabLabel =>
      'concierge.customerServiceTabLabel'.tr;
  static String get howMayIAssist => 'concierge.howMayIAssist'.tr;
  static String get helpDescription => 'concierge.helpDescription'.tr;
  static String get conciergeComingSoon => 'concierge.comingSoon'.tr;

  // Booking / Reservation
  static String get findBookingTitle => 'booking.findBookingTitle'.tr;
  static String get findBookingSubtitle => 'booking.findBookingSubtitle'.tr;
  static String get reservationCodeLabel => 'booking.reservationCodeLabel'.tr;
  static String get reservationCodeHint => 'booking.reservationCodeHint'.tr;
  static String get findReservationButtonLabel =>
      'booking.findReservationButtonLabel'.tr;
  static String get yourReservationTitle => 'booking.yourReservationTitle'.tr;
  static String get yourReservationSubtitle =>
      'booking.yourReservationSubtitle'.tr;
  static String get haveReservationTitle => 'booking.haveReservationTitle'.tr;
  static String get haveReservationSubtitle =>
      'booking.haveReservationSubtitle'.tr;
  static String get noReservationTitle => 'booking.noReservationTitle'.tr;
  static String get noReservationSubtitle => 'booking.noReservationSubtitle'.tr;

  // Settings
  static String get settings => 'settings.settings'.tr;
  static String get language => 'settings.language'.tr;
  static String get currency => 'settings.currency'.tr;

  //File Service

  static const String updateAvailable = 'updateAvailable';
  static const String later = 'later';
  static const String updateNow = 'updateNow';

  // ── Check-in wizard ──
  static String get checkInTitle => 'checkIn.title'.tr;
  static String get checkInTabIdentity => 'checkIn.tabIdentity'.tr;
  static String get checkInTabPreferences => 'checkIn.tabPreferences'.tr;
  static String get checkInTabRoomKey => 'checkIn.tabRoomKey'.tr;
  static String get identityTitle => 'checkIn.identityTitle'.tr;
  static String get identitySubtitle => 'checkIn.identitySubtitle'.tr;
  static String get yourBooking => 'checkIn.yourBooking'.tr;
  static String get guestLabel => 'checkIn.guest'.tr;
  static String get roomLabel => 'checkIn.room'.tr;
  static String get checkInLabel => 'checkIn.checkInLabel'.tr;
  static String get checkOutLabel => 'checkIn.checkOutLabel'.tr;
  static String get tapToScanId => 'checkIn.tapToScanId'.tr;
  static String get passportOrNationalId => 'checkIn.passportOrNationalId'.tr;
  static String get scanId => 'checkIn.scanId'.tr;
  static String get identitySecurityNote => 'checkIn.securityNote'.tr;
  static String get continueToPreferences => 'checkIn.continueToPreferences'.tr;
  static String get identityVerified => 'checkIn.identityVerified'.tr;
  static String get scanYourId => 'checkIn.scanYourId'.tr;
  static String get positionIdInFrame => 'checkIn.positionIdInFrame'.tr;
  static String get scanningLabel => 'checkIn.scanning'.tr;
  static String get dontMoveId => 'checkIn.dontMoveId'.tr;
  static String get scanSuccessful => 'checkIn.scanSuccessful'.tr;
  static String get capturedIdDetails => 'checkIn.capturedIdDetails'.tr;
  static String get frontOfId => 'checkIn.frontOfId'.tr;
  static String get editLabel => 'checkIn.edit'.tr;
  static String get makeSureDetailsClear => 'checkIn.makeSureDetailsClear'.tr;
  static String get continueLabel => 'checkIn.continueLabel'.tr;
  static String get scanAgain => 'checkIn.scanAgain'.tr;
  static String get startingCamera => 'checkIn.startingCamera'.tr;
  static String get cameraUnavailable => 'checkIn.cameraUnavailable'.tr;
  static String get cameraPermissionDenied =>
      'checkIn.cameraPermissionDenied'.tr;
  static String get cameraNoDevice => 'checkIn.cameraNoDevice'.tr;
  static String get cameraFailed => 'checkIn.cameraFailed'.tr;
  static String get cameraCaptureFailed => 'checkIn.cameraCaptureFailed'.tr;
  static String get openSettings => 'checkIn.openSettings'.tr;
  static String get tryAgain => 'checkIn.tryAgain'.tr;
  static String get flashLabel => 'checkIn.flash'.tr;
  static String get scanLabel => 'checkIn.scan'.tr;
  static String get uploadLabel => 'checkIn.upload'.tr;
  static String get bedType => 'checkIn.bedType'.tr;
  static String get pillowLabel => 'checkIn.pillow'.tr;
  static String get mattressType => 'checkIn.mattressType'.tr;
  static String get roomType => 'checkIn.roomType'.tr;
  static String get smokingRoom => 'checkIn.smokingRoom'.tr;
  static String get smokingRoomSubtitle => 'checkIn.smokingRoomSubtitle'.tr;
  static String get earlyCheckIn => 'checkIn.earlyCheckIn'.tr;
  static String get earlyCheckInSubtitle => 'checkIn.earlyCheckInSubtitle'.tr;
  static String get lateCheckOut => 'checkIn.lateCheckOut'.tr;
  static String get lateCheckOutSubtitle => 'checkIn.lateCheckOutSubtitle'.tr;
  static String get extraPillows => 'checkIn.extraPillows'.tr;
  static String get extraPillowsSubtitle => 'checkIn.extraPillowsSubtitle'.tr;
  static String get specialRequestsTitle => 'checkIn.specialRequests'.tr;
  static String get specialRequestsHint => 'checkIn.specialRequestsHint'.tr;
  static String get yourRoomKey => 'checkIn.yourRoomKey'.tr;
  static String get roomKeySubtitle => 'checkIn.roomKeySubtitle'.tr;
  static String get keyCardWaitingTitle => 'checkIn.keyCardWaitingTitle'.tr;
  static String keyCardRoomLabel(String number) =>
      'checkIn.keyCardRoomLabel'.trParams({'number': number});
  static String get keyCardWaitingBody => 'checkIn.keyCardWaitingBody'.tr;
  static String get addDigitalKey => 'checkIn.addDigitalKey'.tr;
  static String get activatingDigitalKey => 'checkIn.activatingDigitalKey'.tr;
  static String get activatedDigitalKey => 'checkIn.activatedDigitalKey'.tr;
  static String get digitalKeyHint => 'checkIn.digitalKeyHint'.tr;
  static String get digitalKeyNotIssued => 'checkIn.digitalKeyNotIssued'.tr;
  static String get completeCheckIn => 'checkIn.completeCheckIn'.tr;
  static String finishStepsFirst(String step) =>
      'checkIn.finishStepsFirst'.trParams({'step': step});
  static String get checkInAwaitingConfirmation =>
      'checkIn.awaitingConfirmation'.tr;
  static String get stepIdentity => 'checkIn.stepIdentity'.tr;
  static String get stepSpecialRequests => 'checkIn.stepSpecialRequests'.tr;
  static String get stepContactDetails => 'checkIn.stepContactDetails'.tr;
  static String get selectArrivalTime => 'checkIn.selectArrivalTime'.tr;
  static String get arrivalTimeSubtitle => 'checkIn.arrivalTimeSubtitle'.tr;
  static String get arrivalEarlyHours => 'checkIn.arrivalEarlyHours'.tr;
  static String get arrivalMorning => 'checkIn.arrivalMorning'.tr;
  static String get arrivalAfternoon => 'checkIn.arrivalAfternoon'.tr;
  static String get arrivalEvening => 'checkIn.arrivalEvening'.tr;
  static String get arrivalLateNight => 'checkIn.arrivalLateNight'.tr;
  static String get onlineCheckInNotConfirmed =>
      'checkIn.onlineNotConfirmed'.tr;
  static String get onlineCheckInClosed => 'checkIn.onlineClosed'.tr;
  static String get checkInOpensOnArrival => 'checkIn.opensOnArrival'.tr;
  static String get checkInRoomNotReady => 'checkIn.roomNotReady'.tr;
  static String get verifiedContactLocked => 'profile.verifiedContactLocked'.tr;
  static String get transferNeedsConfirmed => 'transfer.needsConfirmed'.tr;
  static String get transferUnavailable => 'transfer.unavailable'.tr;
  static String arrivalAfterTime(String time) =>
      'checkIn.arrivalAfterTime'.trParams({'time': time});
  static String get confirmArrivalTime => 'checkIn.confirmArrivalTime'.tr;

  // ── Home pre-arrival ──
  static String get preCheckInAvailable => 'preArrival.available'.tr;
  static String roomChip(String number) =>
      'preArrival.roomChip'.trParams({'number': number});
  static String get bookingRefLabel => 'preArrival.bookingRef'.tr;
  static String get preArrivalProgress => 'preArrival.progress'.tr;
  static String completeFraction(int done, int total) =>
      'preArrival'
              '.completeFraction'
          .trParams({'done': '$done', 'total': '$total'});
  static String get checkInNow => 'preArrival.checkInNow'.tr;
  static String get preArrivalChecklist => 'preArrival.checklist'.tr;
  static String get confirmContactDetails =>
      'preArrival.confirmContactDetails'.tr;
  static String get completedLabel => 'preArrival.completed'.tr;
  static String get uploadIdOrPassport => 'preArrival.uploadIdOrPassport'.tr;
  static String get requiredForCheckIn => 'preArrival.requiredForCheckIn'.tr;
  static String get setArrivalTime => 'preArrival.setArrivalTime'.tr;
  static String get tapToAddEta => 'preArrival.tapToAddEta'.tr;
  static String get preArrivalSpecialRequests =>
      'preArrival.specialRequests'.tr;
  static String get optionalPreferences => 'preArrival.optionalPreferences'.tr;
  static String get confirmedStatus => 'preArrival.confirmedStatus'.tr;
  static String get airportTransfer => 'preArrival.airportTransfer'.tr;
  static String get airportTransferBody => 'preArrival.airportTransferBody'.tr;
  static String get requestAirportTransfer =>
      'preArrival.requestAirportTransfer'.tr;

  // ══ Extracted from hardcoded Dart literals ══

  // Shared labels
  static String get total => 'general.total'.tr;
  static String get retry => 'general.retry'.tr;
  static String get copy => 'general.copy'.tr;
  static String get copied => 'general.copied'.tr;
  static String get close => 'general.close'.tr;
  static String get back => 'general.back'.tr;
  static String get guest => 'general.guest'.tr;
  static String get date => 'general.date'.tr;
  static String get time => 'general.time'.tr;
  static String get guests => 'general.guests'.tr;
  static String get about => 'general.about'.tr;
  static String get hours => 'general.hours'.tr;
  static String get gallery => 'general.gallery'.tr;
  static String get active => 'general.active'.tr;
  static String get past => 'general.past'.tr;
  static String get request => 'general.request'.tr;
  static String get call => 'general.call'.tr;
  static String get apply => 'general.apply'.tr;
  static String get reserve => 'general.reserve'.tr;
  static String get reviews => 'general.reviews'.tr;

  // Account
  static String get myProfile => 'account.myProfile'.tr;
  static String get personalInformation => 'account.personalInformation'.tr;
  static String get notAdded => 'account.notAdded'.tr;
  static String get verified => 'account.verified'.tr;
  static String get editProfile => 'account.editProfile'.tr;
  static String get saveChanges => 'account.saveChanges'.tr;
  static String get profileUpdated => 'account.profileUpdated'.tr;
  static String get security => 'account.security'.tr;
  static String get helpAndSupport => 'account.helpAndSupport'.tr;
  static String get legal => 'account.legal'.tr;
  static String get signOut => 'account.signOut'.tr;

  // Preference catalogue
  static String get bedKing => 'prefs.bedKing'.tr;
  static String get bedQueen => 'prefs.bedQueen'.tr;
  static String get bedDouble => 'prefs.bedDouble'.tr;
  static String get bedTwin => 'prefs.bedTwin'.tr;
  static String get bedSingle => 'prefs.bedSingle'.tr;
  static String get pillowSoft => 'prefs.pillowSoft'.tr;
  static String get pillowFirm => 'prefs.pillowFirm'.tr;
  static String get pillowFeather => 'prefs.pillowFeather'.tr;
  static String get pillowMedium => 'prefs.pillowMedium'.tr;
  static String get pillowHypoallergenic => 'prefs.pillowHypoallergenic'.tr;
  static String get floorLabel => 'prefs.floorLabel'.tr;
  static String get floorAny => 'prefs.floorAny'.tr;
  static String get floorLow => 'prefs.floorLow'.tr;
  static String get floorHigh => 'prefs.floorHigh'.tr;
  static String get mattressSoft => 'prefs.mattressSoft'.tr;
  static String get mattressMedium => 'prefs.mattressMedium'.tr;
  static String get mattressFirm => 'prefs.mattressFirm'.tr;
  static String get mattressMemory => 'prefs.mattressMemory'.tr;
  static String get mattressOrtho => 'prefs.mattressOrtho'.tr;
  static String get mattressStandard => 'prefs.mattressStandard'.tr;

  // Booking flow
  static String get selectDatesAndGuests => 'book.selectDatesAndGuests'.tr;
  static String get adults => 'book.adults'.tr;
  static String get ages18Plus => 'book.ages18Plus'.tr;
  static String get children => 'book.children'.tr;
  static String get ages0To17 => 'book.ages0To17'.tr;
  static String get chooseYourRoom => 'book.chooseYourRoom'.tr;
  static String get selectRoom => 'book.selectRoom'.tr;
  static String get selectThisRoom => 'book.selectThisRoom'.tr;
  static String get backToRooms => 'book.backToRooms'.tr;
  static String get guestDetails => 'book.guestDetails'.tr;
  static String get emailAddressRequired => 'book.emailAddressRequired'.tr;
  static String get addOns => 'book.addOns'.tr;
  static String get extrasTotal => 'book.extrasTotal'.tr;
  static String get skipNoExtras => 'book.skipNoExtras'.tr;
  static String get reviewBooking => 'book.reviewBooking'.tr;
  static String get bookingConfirmed => 'book.bookingConfirmed'.tr;
  static String get confirmationCode => 'book.confirmationCode'.tr;
  static String get viewMyStays => 'book.viewMyStays'.tr;
  static String get selectYourDates => 'book.selectYourDates'.tr;
  static String get selectYourDatesFirst => 'book.selectYourDatesFirst'.tr;
  static String get promoCode => 'book.promoCode'.tr;
  static String get highlights => 'book.highlights'.tr;
  static String get allAmenities => 'book.allAmenities'.tr;
  static String get hidePriceDetails => 'book.hidePriceDetails'.tr;
  static String get viewPriceDetails => 'book.viewPriceDetails'.tr;

  // Payment
  static String get cardNumber => 'payment.cardNumber'.tr;
  static String get expiryDate => 'payment.expiryDate'.tr;
  static String get cardholder => 'payment.cardholder'.tr;
  static String get cardholderHint => 'payment.cardholderHint'.tr;
  static String get expires => 'payment.expires'.tr;
  static String get creditCard => 'payment.creditCard'.tr;
  static String get applePaySubtitle => 'payment.applePaySubtitle'.tr;
  static String get applePayFaceId => 'payment.applePayFaceId'.tr;
  static String get applePayNoCardShared => 'payment.applePayNoCardShared'.tr;
  static String get applePayTagline => 'payment.applePayTagline'.tr;
  static String get googlePaySecurity => 'payment.googlePaySecurity'.tr;
  static String get payAtHotel => 'payment.payAtHotel'.tr;
  static String get howItWorks => 'payment.howItWorks'.tr;
  static String get acceptedCards => 'payment.acceptedCards'.tr;
  static String get acceptedCardsFull => 'payment.acceptedCardsFull'.tr;
  static String get acceptedCash => 'payment.acceptedCash'.tr;

  // Stays
  static String get noActiveStay => 'stays.noActiveStay'.tr;
  static String get bookAStay => 'stays.bookAStay'.tr;
  static String get noPastStays => 'stays.noPastStays'.tr;
  static String get viewReceipt => 'stays.viewReceipt'.tr;
  static String get bookAgain => 'stays.bookAgain'.tr;
  static String get cancelReservation => 'stays.cancelReservation'.tr;
  static String get totalCharged => 'stays.totalCharged'.tr;
  static String get requestServiceCaps => 'stays.requestServiceCaps'.tr;
  static String get expressCheckoutCaps => 'stays.expressCheckoutCaps'.tr;
  static String get completedCaps => 'stays.completedCaps'.tr;
  static String get cancelledCaps => 'stays.cancelledCaps'.tr;
  static String get receipt => 'stays.receipt'.tr;
  static String get downloadPdfReceipt => 'stays.downloadPdfReceipt'.tr;
  static String get noKeep => 'stays.noKeep'.tr;
  static String get yesCancel => 'stays.yesCancel'.tr;
  static String get checkoutComplete => 'stays.checkoutComplete'.tr;
  static String get noActiveStayToCheckOut => 'stays.noActiveStayToCheckOut'.tr;

  // Home dashboard
  static String get yourStay => 'home.yourStay'.tr;
  static String get myBill => 'home.myBill'.tr;
  static String get checkout => 'home.checkout'.tr;
  static String get estimatedTotal => 'home.estimatedTotal'.tr;
  static String get activeRequests => 'home.activeRequests'.tr;
  static String get newRequest => 'home.newRequest'.tr;
  static String get quickRequests => 'home.quickRequests'.tr;
  static String get askTheConcierge => 'home.askTheConcierge'.tr;

  // Dining & reviews
  static String get reserveATable => 'dining.reserveATable'.tr;
  static String get confirmReservation => 'dining.confirmReservation'.tr;
  static String get writeAReview => 'reviews.writeAReview'.tr;
  static String get submitReview => 'reviews.submitReview'.tr;
  static String get noReviewsYet => 'reviews.noReviewsYet'.tr;
  static String get verifiedStay => 'reviews.verifiedStay'.tr;

  // Service catalogue tiles
  static String get sendRequest => 'services.sendRequest'.tr;
  static String get requestTargetRoom => 'services.requestTargetRoom'.tr;
  static String get tileRoomService => 'services.tileRoomService'.tr;
  static String get tileLaundry => 'services.tileLaundry'.tr;
  static String get tileHousekeeping => 'services.tileHousekeeping'.tr;
  static String get tileConcierge => 'services.tileConcierge'.tr;
  static String get tileTransport => 'services.tileTransport'.tr;
  static String get tileRestaurant => 'services.tileRestaurant'.tr;
  static String get tileMaintenance => 'services.tileMaintenance'.tr;
  static String get sub24Hrs => 'services.sub24Hrs'.tr;
  static String get subSameDay => 'services.subSameDay'.tr;
  static String get subOnDemand => 'services.subOnDemand'.tr;
  static String get subAlwaysAvailable => 'services.subAlwaysAvailable'.tr;
  static String get subCarAndValet => 'services.subCarAndValet'.tr;
  static String get subQuickRequest => 'services.subQuickRequest'.tr;
  static String get subPrivacyMode => 'services.subPrivacyMode'.tr;

  // Concierge chat
  static String get imageTooLarge => 'concierge.imageTooLarge'.tr;

  // Notifications
  static String get channelName => 'notifications.channelName'.tr;

  // ── Parameterised (extracted) ──

  /// `1 Adult` / `@count Adults`. English two-form is enough here: the count
  /// is bounded by the room's max occupancy, so the Arabic dual/few/many
  /// distinctions never surface for realistic values.
  static String adultsCount(int count) => count == 1
      ? 'book.adultsCountOne'.tr
      : 'book.adultsCount'.trParams({'count': '$count'});

  static String childrenCount(int count) => count == 1
      ? 'book.childrenCountOne'.tr
      : 'book.childrenCount'.trParams({'count': '$count'});

  static String promoCodeApplied(String code) =>
      'book.promoCodeApplied'.trParams({'code': code});

  static String roomSize(String size) =>
      'book.roomSize'.trParams({'size': size});

  static String roomView(String view) =>
      'book.roomView'.trParams({'view': view});

  static String roomBed(String bed) => 'book.roomBed'.trParams({'bed': bed});

  static String creditCardMasked(String last4) =>
      'payment.creditCardMasked'.trParams({'last4': last4});

  static String resCode(String code) =>
      'stays.resCode'.trParams({'code': code});

  static String stayRoomNumber(String number) =>
      'stays.roomNumber'.trParams({'number': number});

  static String suiteNumber(String number) =>
      'stays.suiteNumber'.trParams({'number': number});

  static String checkoutConfirmBody(String target) =>
      'home.checkoutConfirmBody'.trParams({'target': target});

  static String retryAfterSeconds(int count) =>
      'api.retryAfterSeconds'.trParams({'count': '$count'});

  // ── Batch 2 ──
  static String get searchRooms => 'book.searchRooms'.tr;
  static String get bankWire => 'book.bankWire'.tr;
  static String get walletTagline => 'payment.walletTagline'.tr;
  static String get upcoming => 'stays.upcoming'.tr;
  static String get noActiveStaySubtitle => 'stays.noActiveStaySubtitle'.tr;
  static String get noPastStaysSubtitle => 'stays.noPastStaysSubtitle'.tr;
  static String get tabMenu => 'dining.tabMenu'.tr;
  static String get tabInfo => 'dining.tabInfo'.tr;

  /// `1 night` / `@count nights` — same two-form reasoning as [adultsCount].
  static String nightsCount(int count) => count == 1
      ? 'book.nightsCountOne'.tr
      : 'book.nightsCount'.trParams({'count': '$count'});

  /// `1 guest` / `@count guests` — same two-form reasoning as [adultsCount].
  static String guestsCount(int count) => count == 1
      ? 'general.guestsCountOne'.tr
      : 'general.guestsCount'.trParams({'count': '$count'});

  static String get googlePayTagline => 'payment.googlePayTagline'.tr;
  static String get payAtHotelTagline => 'payment.payAtHotelTagline'.tr;
  static String get statusRequested => 'services.statusRequested'.tr;
  static String get statusInProgress => 'services.statusInProgress'.tr;
  static String get statusConfirmed => 'services.statusConfirmed'.tr;
  static String get statusCompleted => 'services.statusCompleted'.tr;
  static String get statusCancelled => 'services.statusCancelled'.tr;
  static String get stayStatusCompleted => 'stays.statusCompleted'.tr;
  static String get stayStatusCancelled => 'stays.statusCancelled'.tr;

  static String get notificationChannelName => 'notifications.channelName'.tr;

  static String get signOutTitle => 'account.signOutTitle'.tr;
  static String get signOutBody => 'account.signOutBody'.tr;
  static String get googlePaySubtitle => 'payment.googlePaySubtitle'.tr;
  static String get googlePaySavedMethod => 'payment.googlePaySavedMethod'.tr;
  static String get googlePayInstant => 'payment.googlePayInstant'.tr;
  static String get checkoutStatementNote => 'home.checkoutStatementNote'.tr;
  static String get loadStayFailed => 'stays.loadStayFailed'.tr;
  static String get loadReservationsFailed => 'stays.loadReservationsFailed'.tr;
  static String get loadPastFailed => 'stays.loadPastFailed'.tr;
  static String get loadMessagesFailed => 'concierge.loadMessagesFailed'.tr;

  // ── Currency ──
  static String get currencyUsd => 'currency.usd'.tr;
  static String get currencySyp => 'currency.syp'.tr;
  static String get currencyTry => 'currency.try'.tr;

  /// Display name for a currency code, so the picker follows the language
  /// switch instead of carrying English names in the model.
  static String currencyName(String code) => switch (code) {
    'usd' => currencyUsd,
    'syp' => currencySyp,
    'try' => currencyTry,
    _ => code.toUpperCase(),
  };

  static String perNight(String price) =>
      'book.perNight'.trParams({'price': price});

  /// The `/night` tail on its own, for a price drawn in a separate style.
  static String get perNightSuffix => 'book.perNightSuffix'.tr;

  /// A room-type `view_type` code (`city`, `pool`, …) in the app language.
  /// An unknown code shows as sent rather than disappearing.
  static String viewTypeName(String code) {
    final key = 'book.view.$code';
    final translated = key.tr;
    return translated == key ? code : translated;
  }

  static String balanceDue(String amount) =>
      'stays.balanceDue'.trParams({'amount': amount});

  // -- Batch 3: named-parameter strings the literal pass missed --
  static String get notifications => 'account.notifications'.tr;
  static String get savedPayments => 'account.savedPayments'.tr;
  static String get notificationsEmptyTitle =>
      'account.notificationsEmptyTitle'.tr;
  static String get notificationsEmptySubtitle =>
      'account.notificationsEmptySubtitle'.tr;
  static String get savedPaymentsEmptyTitle =>
      'account.savedPaymentsEmptyTitle'.tr;
  static String get savedPaymentsEmptySubtitle =>
      'account.savedPaymentsEmptySubtitle'.tr;
  static String get securityEmptyTitle => 'account.securityEmptyTitle'.tr;
  static String get securityEmptySubtitle => 'account.securityEmptySubtitle'.tr;
  static String get stayPreferences => 'account.stayPreferences'.tr;
  static String get unlockInRoom => 'services.unlockInRoom'.tr;
  static String get checkConnectionRetry => 'api.checkConnectionRetry'.tr;
  static String get noUpcomingStays => 'stays.noUpcoming'.tr;
  static String get noUpcomingStaysSubtitle => 'stays.noUpcomingSubtitle'.tr;
  static String get freeCancellation => 'book.freeCancellation'.tr;
  static String get locationLabel => 'dining.locationLabel'.tr;
  static String get cuisineLabel => 'dining.cuisineLabel'.tr;
  static String get ratingLabel => 'dining.ratingLabel'.tr;
  static String get diningNotesHint => 'dining.notesHint'.tr;
  static String get menuNotAvailable => 'dining.menuNotAvailable'.tr;
  static String get menuOpenFailed => 'dining.menuOpenFailed'.tr;
  static String get signInToReview => 'reviews.signInToReview'.tr;
  static String get reviewsLoadFailed => 'reviews.loadFailed'.tr;
  static String get reviewThankYou => 'reviews.thankYou'.tr;
  static String get reviewShareHint => 'reviews.shareHint'.tr;
  static String get currentBill => 'home.currentBill'.tr;
  static String get fullStatement => 'home.fullStatement'.tr;
  static String get checkoutFailed => 'home.checkoutFailed'.tr;
  static String get requestNotesHint => 'services.requestNotesHint'.tr;
  static String get promoHint => 'book.promoHint'.tr;
  static String get promoCodeFirst => 'book.promoCodeFirst'.tr;
  static String get roomLoadFailed => 'book.roomLoadFailed'.tr;
  static String get signInToBookTitle => 'book.signInToBookTitle'.tr;
  static String get signInToBook => 'book.signInToBook'.tr;
  static String get selectRoomAndDates => 'book.selectRoomAndDates'.tr;
  static String get otpExpired => 'auth.otpExpired'.tr;
  static String get otpTooManyAttempts => 'auth.otpTooManyAttempts'.tr;
  static String get otpIncorrect => 'auth.otpIncorrect'.tr;
  static String get receiptLoadFailed => 'stays.receiptLoadFailed'.tr;
  static String get receiptDownloadFailed => 'stays.receiptDownloadFailed'.tr;
  static String get reservationCancelled => 'stays.reservationCancelled'.tr;
  static String bookingRewardPoints(int points, String amount) =>
      'stays.rewardPoints'.trParams({'points': '$points', 'amount': amount});
  static String bookingRewardVoucher(String code, String amount) =>
      'stays.rewardVoucher'.trParams({'code': code, 'amount': amount});
  static String get bookingRewardUpgrade => 'stays.rewardUpgrade'.tr;
  static String cancelledPointsReturned(int points) =>
      'stays.cancelledPointsBack'.trParams({'points': '$points'});
  static String cancelledVoucherRestored(String code) =>
      'stays.cancelledVoucherBack'.trParams({'code': code});

  /// Each hero title carries one emphasised word, stored as its own key so a
  /// translator can put the emphasis where the phrase actually needs it.
  /// [CustomHomeContainer] splits on `*word*`, so the three parts are
  /// recombined into that markup here rather than teaching the widget a
  /// three-part API.
  static String get heroVideoTitle =>
      '$heroVideoTitlePre*$heroVideoTitleItalic*$heroVideoTitlePost';

  static String get heroDiningTitle =>
      '$heroDiningTitlePre*$heroDiningTitleItalic*$heroDiningTitlePost';

  static String get heroExperiencesTitle =>
      '$heroExperiencesTitlePre*$heroExperiencesTitleItalic*'
      '$heroExperiencesTitlePost';

  // -- Airport Transfer flow --
  static String get transferTitle => 'transfer.title'.tr;
  static String get transferRoute => 'transfer.route'.tr;
  static String get flightDetails => 'transfer.flightDetails'.tr;
  static String get flightNumber => 'transfer.flightNumber'.tr;
  static String get flightNumberHint => 'transfer.flightNumberHint'.tr;
  static String get transferDate => 'transfer.date'.tr;
  static String get arrivalTime => 'transfer.arrivalTime'.tr;
  static String get transferArrivalSubtitle => 'transfer.arrivalSubtitle'.tr;
  static String get terminal => 'transfer.terminal'.tr;
  static String get passengers => 'transfer.passengers'.tr;
  static String get chooseYourCar => 'transfer.chooseYourCar'.tr;
  static String get selectVehicle => 'transfer.selectVehicle'.tr;
  static String get oneWay => 'transfer.oneWay'.tr;
  static String get reviewBookingLabel => 'transfer.reviewBooking'.tr;
  static String get confirmTransfer => 'transfer.confirmTransfer'.tr;
  static String get pickup => 'transfer.pickup'.tr;
  static String get pickupValue => 'transfer.pickupValue'.tr;
  static String get destination => 'transfer.destination'.tr;
  static String get destinationValue => 'transfer.destinationValue'.tr;
  static String get flightLabel => 'transfer.flight'.tr;
  static String get notSpecified => 'transfer.notSpecified'.tr;
  static String get dateAndTime => 'transfer.dateAndTime'.tr;
  static String get vehicle => 'transfer.vehicle'.tr;
  static String get transferInstructions => 'transfer.specialInstructions'.tr;
  static String get transferInstructionsHint => 'transfer.instructionsHint'.tr;
  static String get transferFolioNote => 'transfer.folioNote'.tr;
  static String get confirmBooking => 'transfer.confirmBooking'.tr;
  static String get selectVehicleFirst => 'transfer.selectVehicleFirst'.tr;
  static String get noTransfers => 'transfer.noVehicles'.tr;
  static String get transferLoadFailed => 'transfer.loadFailed'.tr;
  static String get transferBooked => 'transfer.booked'.tr;
  static String get transferAwaitingConfirmation =>
      'transfer.awaitingConfirmation'.tr;
  static String get transferTimeInPast => 'transfer.timeInPast'.tr;

  static String maxPassengers(int count) =>
      'transfer.maxPassengers'.trParams({'count': '$count'});

  static String upToPassengers(int count) =>
      'transfer.upToPassengers'.trParams({'count': '$count'});

  static String terminalValue(String name) =>
      'transfer.terminalValue'.trParams({'name': name});

  /// `1 passenger` / `@count passengers` — two-form is enough here, the count
  /// is capped at the vehicle's seat count.
  static String passengerCount(int count) => count == 1
      ? 'transfer.passengerCountOne'.tr
      : 'transfer.passengerCount'.trParams({'count': '$count'});

  /// Joins date and time on the transfer summary line.
  static String get at => 'transfer.at'.tr;

  /// `1 night remaining` / `@count nights remaining` — the active-stay hero.
  static String nightsRemainingCount(int count) => count == 1
      ? 'stays.nightsRemainingOne'.tr
      : 'stays.nightsRemainingCount'.trParams({'count': '$count'});

  // Loyalty / Carlton Rewards
  static String get loyaltyTitle => 'loyalty.title'.tr;
  static String get loyaltyRow => 'loyalty.row'.tr;
  static String get loyaltyRowBody => 'loyalty.rowBody'.tr;
  static String get loyaltyProgramName => 'loyalty.programName'.tr;
  static String get loyaltyAvailablePoints => 'loyalty.availablePoints'.tr;
  static String get loyaltyPointsUnit => 'loyalty.pointsUnit'.tr;
  static String get loyaltyEarnTitle => 'loyalty.earnTitle'.tr;
  static String get loyaltyEarnBody => 'loyalty.earnBody'.tr;
  static String get loyaltyEarned => 'loyalty.earned'.tr;
  static String get loyaltyRedeemed => 'loyalty.redeemed'.tr;
  static String get loyaltyActivity => 'loyalty.activity'.tr;
  static String get loyaltyNoActivity => 'loyalty.noActivity'.tr;
  static String get loyaltyNoActivityBody => 'loyalty.noActivityBody'.tr;
  static String get loyaltyRedeem => 'loyalty.redeem'.tr;

  /// `@points pts to @tier` — the caption under the tier bar. [points] is
  /// pre-formatted by `formatPoints()` so the grouping follows the guest's
  /// locale rather than being interpolated raw here.
  /// `@count Total` — the entry count on the ledger header.
  // ── Loyalty (live API) ────────────────────────────────────────────────
  static String loyaltyWorth(String amount) =>
      'loyalty.worth'.trParams({'amount': amount});
  static String loyaltyExpiring({
    required String points,
    required String date,
  }) => 'loyalty.expiring'.trParams({'points': points, 'date': date});
  static String get loyaltyLoadFailed => 'loyalty.loadFailed'.tr;
  static String get loyaltyMyVouchers => 'loyalty.myVouchers'.tr;
  static String get loyaltyRewardsTitle => 'loyalty.rewardsTitle'.tr;
  static String get loyaltyNoRewards => 'loyalty.noRewards'.tr;
  static String get loyaltyNoRewardsBody => 'loyalty.noRewardsBody'.tr;
  static String get loyaltyNoVouchers => 'loyalty.noVouchers'.tr;
  static String get loyaltyNoVouchersBody => 'loyalty.noVouchersBody'.tr;
  static String loyaltyShortfall(String points) =>
      'loyalty.shortfall'.trParams({'points': points});
  static String loyaltyValueOff(String amount) =>
      'loyalty.valueOff'.trParams({'amount': amount});
  static String loyaltyPointsCost(String points) =>
      'loyalty.pointsCost'.trParams({'points': points});
  static String get loyaltyVoucherUsed => 'loyalty.voucherUsed'.tr;
  static String get loyaltyVoucherExpired => 'loyalty.voucherExpired'.tr;
  static String get loyaltyVoucherActive => 'loyalty.voucherActive'.tr;
  static String get loyaltyVoucherUnavailable =>
      'loyalty.voucherUnavailable'.tr;
  static String loyaltyVoucherExpires(String date) =>
      'loyalty.voucherExpires'.trParams({'date': date});
  static String get loyaltyConfirmRedeemTitle =>
      'loyalty.confirmRedeemTitle'.tr;
  static String loyaltyConfirmRedeemBody({
    required String points,
    required String reward,
  }) => 'loyalty.confirmRedeemBody'.trParams({
    'points': points,
    'reward': reward,
  });
  static String get loyaltyRedeemReward => 'loyalty.redeemReward'.tr;
  static String get loyaltyVoucherReadyTitle => 'loyalty.voucherReadyTitle'.tr;
  static String loyaltyVoucherCodeIs(String code) =>
      'loyalty.voucherCodeIs'.trParams({'code': code});
  static String get loyaltyCopyCode => 'loyalty.copyCode'.tr;
  static String loyaltyNeedMorePoints(String points) =>
      'loyalty.needMorePoints'.trParams({'points': points});
  static String get loyaltyNotEnoughPoints => 'loyalty.notEnoughPoints'.tr;
  static String get loyaltyRewardUnavailable => 'loyalty.rewardUnavailable'.tr;
  static String get loyaltyUseRewards => 'loyalty.useRewards'.tr;
  static String get loyaltyUseNone => 'loyalty.useNone'.tr;
  static String get loyaltyUsePoints => 'loyalty.usePoints'.tr;
  static String get loyaltyUseVoucher => 'loyalty.useVoucher'.tr;
  static String get loyaltyPointsToUse => 'loyalty.pointsToUse'.tr;
  static String get loyaltyVoucherCodeHint => 'loyalty.voucherCodeHint'.tr;
  static String loyaltyAvailableLine(String points) =>
      'loyalty.availableLine'.trParams({'points': points});
  static String get loyaltyPointsDiscount => 'loyalty.pointsDiscount'.tr;
  static String get loyaltyVoucherDiscount => 'loyalty.voucherDiscount'.tr;
  static String get loyaltyUpgradeRequested => 'loyalty.upgradeRequested'.tr;
  static String get loyaltyNetTotal => 'loyalty.netTotal'.tr;
  static String loyaltyEarnEstimate(String points) =>
      'loyalty.earnEstimate'.trParams({'points': points});
  static String loyaltyBelowMinimum(String points) =>
      'loyalty.belowMinimum'.trParams({'points': points});
  static String loyaltyOverCap(String points) =>
      'loyalty.overCap'.trParams({'points': points});
  static String loyaltyOnlyHave(String points) =>
      'loyalty.onlyHave'.trParams({'points': points});
  static String get loyaltyVoucherInvalid => 'loyalty.voucherInvalid'.tr;
  static String get loyaltyStillPricing => 'loyalty.stillPricing'.tr;
  static String get loyaltyFixRewards => 'loyalty.fixRewards'.tr;
  static String get loyaltyPointsOff => 'loyalty.pointsOff'.tr;
  static String get loyaltyPointsOrVoucher => 'loyalty.pointsOrVoucher'.tr;

  // ── Full-coverage localization ────────────────────────────────────────
  static String get bookingConfirmedBang => 'book.bookingConfirmedBang'.tr;
  static String get enhanceYourStay => 'book.enhanceYourStay'.tr;
  static String get guestAndPayment => 'book.guestAndPayment'.tr;
  static String get payment => 'book.payment'.tr;
  static String get paymentMethod => 'book.paymentMethod'.tr;
  static String get phoneNumberRequired => 'book.phoneNumberRequired'.tr;
  static String get promoInvalid => 'book.promoInvalid'.tr;
  static String get roomSoldOut => 'book.roomSoldOut'.tr;
  static String roomTooSmall(int count) =>
      'book.roomTooSmall'.trParams({'count': '$count'});
  static String get bookingLinkNotFound => 'auth.bookingLinkNotFound'.tr;
  static String get datesSoldOut => 'book.datesSoldOut'.tr;
  static String get bookingFailed => 'book.bookingFailed'.tr;
  static String get dietaryNotesHint => 'book.dietaryNotesHint'.tr;
  static String get applePayUnavailable => 'book.applePayUnavailable'.tr;
  static String get googlePayUnavailable => 'book.googlePayUnavailable'.tr;
  static String get cardUnavailable => 'book.cardUnavailable'.tr;
  static String get cardWalletUnavailable => 'book.cardWalletUnavailable'.tr;
  static String confirmationSentTo(String email) =>
      'book.confirmationSentTo'.trParams({'email': email});
  static String addOnsNotBooked(String items) =>
      'book.addOnsNotBooked'.trParams({'items': items});
  static String get bookingReceivedBang => 'book.bookingReceivedBang'.tr;
  static String get awaitingConfirmation => 'stays.awaitingConfirmation'.tr;
  static String get awaitingHotelConfirmation =>
      'book.awaitingHotelConfirmation'.tr;
  static String addOnsAfterConfirmation(String items) =>
      'book.addOnsAfterConfirmation'.trParams({'items': items});
  static String get soldOut => 'cards.soldOut'.tr;
  static String upToGuests(int count) =>
      'cards.upToGuests'.trParams({'count': '$count'});
  static String get lastRoom => 'cards.lastRoom'.tr;
  static String roomsLeft(String count) =>
      'cards.roomsLeft'.trParams({'count': count});
  static String get yourNameCaps => 'cards.yourName'.tr;
  static String get beFirstToReview => 'dining.beFirstToReview'.tr;
  static String get diningReviewsLoadFailed => 'dining.reviewsLoadFailed'.tr;
  static String get detailsComingSoon => 'dining.detailsComingSoon'.tr;
  static String get downloadFullMenu => 'dining.downloadFullMenu'.tr;
  static String get specialRequestsOptional =>
      'dining.specialRequestsOptional'.tr;
  static String get vegan => 'dining.vegan'.tr;
  static String get viewMenu => 'discover.viewMenu'.tr;
  static String get viewOffer => 'discover.viewOffer'.tr;
  static String get folioLoadFailed => 'folio.loadFailed'.tr;
  static String get folioEmpty => 'folio.empty'.tr;
  static String get folioEmptySubtitle => 'folio.emptySubtitle'.tr;
  static String get subtotal => 'folio.subtotal'.tr;
  static String get folioApproved => 'folio.approvedForCheckout'.tr;
  static String get folioSourceRoom => 'folio.sourceRoomCharge'.tr;
  static String get folioSourceService => 'folio.sourceService'.tr;
  static String get folioSourceInRoom => 'folio.sourceInRoomRequest'.tr;
  static String get folioSourceDesk => 'folio.sourceDesk'.tr;
  static String get folioSourceCredit => 'folio.sourceCredit'.tr;
  static String get checkoutAtDesk => 'folio.checkoutAtDesk'.tr;
  static String folioQuantity(int qty, String price) =>
      'folio.quantity'.trParams({'qty': '$qty', 'price': price});
  static String folioPayment(String method) =>
      'folio.payment'.trParams({'method': method});
  static String get folioPaid => 'folio.paid'.tr;
  static String get folioBalanceDue => 'folio.balanceDue'.tr;
  static String get folioHotelOwes => 'folio.hotelOwes'.tr;
  static String get disputeHint => 'folio.disputeHint'.tr;
  static String get disputeCharge => 'folio.disputeCharge'.tr;
  static String get disputeInfo => 'folio.disputeInfo'.tr;
  static String get disputeReasonLabel => 'folio.disputeReasonLabel'.tr;
  static String get disputeReasonHint => 'folio.disputeReasonHint'.tr;
  static String get disputeReasonRequired => 'folio.disputeReasonRequired'.tr;
  static String get sendDispute => 'folio.sendDispute'.tr;
  static String get disputeSent => 'folio.disputeSent'.tr;
  static String get disputeAlreadyOpen => 'folio.disputeAlreadyOpen'.tr;
  static String get disputeUnderReview => 'folio.disputeUnderReview'.tr;
  static String disputesOpenCount(int count) =>
      'folio.disputesOpen'.trParams({'count': '$count'});
  static String get cardSecurityNote => 'general.cardSecurityNote'.tr;
  static String get acceptedPaymentMethods =>
      'general.acceptedPaymentMethods'.tr;
  static String get askMeAnything => 'general.askMeAnything'.tr;
  static String get cvv => 'general.cvv'.tr;
  static String get nameOnCard => 'general.nameOnCard'.tr;
  static String get finalTotalForStay => 'general.finalTotalForStay'.tr;
  static String get freeCancellation48h => 'general.freeCancellation48h'.tr;
  static String get includesTaxesAndFees => 'general.includesTaxesAndFees'.tr;
  static String get noPaymentRequiredNow => 'general.noPaymentRequiredNow'.tr;
  static String get paymentAtFrontDesk => 'general.paymentAtFrontDesk'.tr;
  static String get reservationSecuredNoCharge =>
      'general.reservationSecuredNoCharge'.tr;
  static String get checkConnectionShort => 'general.checkConnectionShort'.tr;
  static String get aiConciergeTitle => 'home.aiConciergeTitle'.tr;
  static String get restaurantsLoadFailed => 'home.restaurantsLoadFailed'.tr;
  static String get roomsLoadFailed => 'home.roomsLoadFailed'.tr;
  static String get dndNeedsActiveStay => 'home.dndNeedsActiveStay'.tr;
  static String get noRoomsAvailable => 'home.noRoomsAvailable'.tr;
  static String get roomsAppearWhenOpen => 'home.roomsAppearWhenOpen'.tr;
  static String get noRestaurantsYet => 'home.noRestaurantsYet'.tr;
  static String get venuesListedSoon => 'home.venuesListedSoon'.tr;
  static String get dndOff => 'home.dndOff'.tr;
  static String get dndOn => 'home.dndOn'.tr;
  static String get offersPackages => 'home.offersPackages'.tr;
  static String get sendAMessage => 'home.sendAMessage'.tr;
  static String get guestRelationsWillReply =>
      'home.guestRelationsWillReply'.tr;
  static String get startConversation => 'home.startConversation'.tr;
  static String get nightsLeft => 'home.nightsLeft'.tr;
  static String get legalLoadFailed => 'legal.loadFailed'.tr;
  static String get yourRating => 'reviews.yourRating'.tr;
  static String get yourReviewOptional => 'reviews.yourReviewOptional'.tr;
  static String get cancelReservationTitle =>
      'sheets.cancelReservationTitle'.tr;
  static String cancelReservationBody(String room) =>
      'sheets.cancelReservationBody'.trParams({'room': room});
  static String cancelReservationBodyDated(String room, String dates) =>
      'sheets.cancelReservationBodyDated'.trParams({
        'room': room,
        'dates': dates,
      });
  static String get freeCancellationNoCharges =>
      'sheets.freeCancellationNoCharges'.tr;
  static String requestFor(String target) =>
      'sheets.requestFor'.trParams({'target': target});
  static String teamWithinMinutes(String eta) =>
      'sheets.teamWithinMinutes'.trParams({'eta': eta});
  static String get teamShortly => 'sheets.teamShortly'.tr;
  static String get offerTerms => 'sheets.offerTerms'.tr;
  static String get receiptHotelName => 'stays.receiptHotelName'.tr;
  static String get checkoutConfirmShort => 'stays.checkoutConfirmShort'.tr;
  static String get cancelFailed => 'stays.cancelFailed'.tr;
  static String get preOrderBeforeArrival => 'stays.preOrderBeforeArrival'.tr;
  static String get settledAtFrontDesk => 'stays.settledAtFrontDesk'.tr;
  static String get notCancellable => 'stays.notCancellable'.tr;
  static String get supportLoadFailed => 'support.loadFailed'.tr;
  static String get supportGeneral => 'support.general'.tr;
  static String get messageUs => 'support.messageUs'.tr;
  static String get supportEmpty => 'support.empty'.tr;
  static String get supportEmptySubtitle => 'support.emptySubtitle'.tr;
  static String get stillNeedHelp => 'support.stillNeedHelp'.tr;
  static String nextCheckInInDays(String days) =>
      'stays.nextCheckInInDays'.trParams({'days': days});

  /// `1 review` / `@count reviews` — same two-form reasoning as [adultsCount].
  static String reviewsCount(int count) => count == 1
      ? 'dining.reviewsCountOne'.tr
      : 'dining.reviewsCount'.trParams({'count': '$count'});

  static String checkedInRoom(String room) =>
      'cards.checkedInRoom'.trParams({'room': room});

  static String paymentProcessed(String method) =>
      'stays.paymentProcessed'.trParams({'method': method});

  static String confirmAndPay(String total) =>
      'book.confirmAndPay'.trParams({'total': total});

  static String continueWithExtras(int count) => count == 1
      ? 'book.continueWithExtrasOne'.tr
      : 'book.continueWithExtras'.trParams({'count': '$count'});

  static String validFrom(String date) =>
      'general.validFrom'.trParams({'date': date});

  static String validUntil(String date) =>
      'general.validUntil'.trParams({'date': date});

  static String passportNumber(String number) =>
      'checkIn.passportNumber'.trParams({'number': number});

  static String totalForNights(int count) => count == 1
      ? 'book.totalForNightsOne'.tr
      : 'book.totalForNights'.trParams({'count': '$count'});

  static String promoLine(String code) =>
      'book.promoLine'.trParams({'code': code});
}
