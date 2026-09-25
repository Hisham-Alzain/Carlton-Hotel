import 'package:get/get.dart';

class AppTranslations {
  // Dialogs
  static String get loading => 'dialogs.loading'.tr;
  static String get error => 'dialogs.error'.tr;
  static String get success => 'dialogs.success'.tr;

  // General
  static String get yes => 'general.yes'.tr;
  static String get no => 'general.no'.tr;
  static String get all => 'general.all'.tr;
  static String get select => 'general.select'.tr;
  static String get search => 'general.search'.tr;
  static String get selected => 'general.selected'.tr;
  static String get searchBy => 'general.searchBy'.tr;
  static String get new_ =>
      'general.new'.tr; // 'new' is a reserved keyword in Dart
  static String get existing => 'general.existing'.tr;
  static String get noItems => 'general.noItems'.tr;
  static String get send => 'general.send'.tr;
  static String get resend => 'general.resend'.tr;
  static String get reset => 'general.reset'.tr;
  static String get and => 'general.and'.tr;
  static String get edit => 'general.edit'.tr;
  static String get delete => 'general.delete'.tr;
  static String get next => 'general.next'.tr;
  static String get change => 'general.change'.tr;
  static String get seeAll => 'general.seeAll'.tr;
  static String get skip => 'general.skip'.tr;
  static String get more => 'general.more'.tr;
  static String get cancel => 'general.cancel'.tr;
  static String get for_ => 'general.for'.tr;
  static String get remove => 'general.remove'.tr;
  static String get submit => 'general.submit'.tr;
  static String get confirm => 'general.confirm'.tr;
  static String get changeNumber => 'general.changeNumber'.tr;
  static String get goBack => 'general.goBack'.tr;
  static String get discoverAll => 'general.discoverAll'.tr;
  static String get bookingComingSoon => 'general.bookingComingSoon'.tr;

  // Validation
  static String get requiredField => 'validation.requiredField'.tr;
  static String get invalidEmail => 'validation.invalidEmail'.tr;
  static String get shortPassword => 'validation.shortPassword'.tr;
  static String get numberField => 'validation.numberField'.tr;
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
  static String get requestId => 'api.requestId'.tr;
  static String get downloading => 'api.downloading'.tr;
  static String get uploading => 'api.uploading'.tr;

  // Auth
  static String get login => 'auth.login'.tr;
  static String get phoneNumber => 'auth.phoneNumber'.tr;
  static String get enterPhone => 'auth.enterPhone'.tr;
  static String get loginSuccessful => 'auth.loginSuccessful'.tr;
  static String get username => 'auth.username'.tr;
  static String get enterUsername => 'auth.enterUsername'.tr;
  static String get password => 'auth.password'.tr;
  static String get enterPassword => 'auth.enterPassword'.tr;
  static String get forgotPassword => 'auth.forgotPassword'.tr;
  static String get logout => 'auth.logout'.tr;
  static String get continueAsGuest => 'auth.continueAsGuest'.tr;
  static String get dontHaveAnAccount => 'auth.dontHaveAnAccount'.tr;
  static String get signUp => 'auth.signUp'.tr;
  static String get logoutSuccessful => 'auth.logoutSuccessful'.tr;
  static String get sessionExpired => 'auth.sessionExpired'.tr;
  static String get pleaseLoginAgain => 'auth.pleaseLoginAgain'.tr;
  static String get logoutConfirmation => 'auth.logoutConfirmation'.tr;
  static String get forgetPasswordTitle => 'auth.forgetPasswordTitle'.tr;
  static String get cantAccessYourPhone => 'auth.cantAccessYourPhone'.tr;
  static String get contactSupport => 'auth.contactSupport'.tr;
  static String get verifyYourNumber => 'auth.verifyYourNumber'.tr;
  static String get fourDigitCode => 'auth.4-digitCode'.tr;
  static String get codeIn => 'auth.codeIn'.tr;
  static String get seconds => 'auth.seconds'.tr;
  static String get verify => 'auth.verify'.tr;
  static String get resetPassword => 'auth.resetPassword'.tr;
  static String get createNewPassword => 'auth.createNewPassword'.tr;
  static String get passwordRequirements => 'auth.passwordRequirements'.tr;
  static String get oldPassword => 'auth.oldPassword'.tr;
  static String get newPassword => 'auth.newPassword'.tr;
  static String get confirmNewPassword => 'auth.confirmNewPassword'.tr;
  static String get confirmPassword => 'auth.confirmPassword'.tr;
  static String get joinCartXForTheBestDeals =>
      'auth.joinCartXForTheBestDeals'.tr;
  static String get fullName => 'auth.fullName'.tr;
  static String get enterFullName => 'auth.enterFullName'.tr;
  static String get email => 'auth.email'.tr;
  static String get enterEmail => 'auth.enterEmail'.tr;
  static String get enterConfirmPassword => 'auth.enterConfirmPassword'.tr;
  static String get iAgreeto => 'auth.iAgreeto'.tr;
  static String get termsAndConditions => 'auth.termsAndConditions'.tr;
  static String get privacyPolicy => 'auth.privacyPolicy'.tr;
  static String get alreadyHaveAnAccount => 'auth.alreadyHaveAnAccount'.tr;
  static String get welcomeToCartX => 'auth.welcomeToCartX'.tr;
  static String get slogan => 'auth.slogan'.tr;
  static String get support => 'auth.support'.tr;
  static String get confirmation => 'auth.confirmation'.tr;
  static String get otpWillBeSentToTheFollowingNumber =>
      'auth.otpWillBeSentToTheFollowingNumber'.tr;
  static String get phone => 'auth.phone'.tr;
  static String get register => 'auth.register'.tr;
  static String get otpVerifiedSuccessfully =>
      'auth.otpVerifiedSuccessfully'.tr;
  static String get otpResentSuccessfully => 'auth.otpResentSuccessfully'.tr;
  static String get passwordResetSuccessfully =>
      'auth.passwordResetSuccessfully'.tr;
  static String get pleaseAgreeToTerms => 'auth.pleaseAgreeToTerms'.tr;
  static String get pleaseLoginToAddToCart => 'auth.pleaseLoginToAddToCart'.tr;
  static String get registrationSuccessful => 'auth.registrationSuccessful'.tr;

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
  static String get codeMismatch => 'auth.codeMismatch'.tr;
  static String resendIn(String time) =>
      'auth.resendIn'.trParams({'time': time});
  static String get resendCodeLink => 'auth.resendCodeLink'.tr;
  static String get verifyButtonLabel => 'auth.verifyButtonLabel'.tr;
  static String get demoNewCodeSent => 'auth.demoNewCodeSent'.tr;
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
  static String get pleaseEnterFirstName => 'auth.pleaseEnterFirstName'.tr;
  static String get pleaseEnterLastName => 'auth.pleaseEnterLastName'.tr;
  static String get phoneNumberHint => 'auth.phoneNumberHint'.tr;
  static String get searchCountryHint => 'auth.searchCountryHint'.tr;

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
  static String get specialOffers => 'home.specialOffers'.tr;
  static String get menuComingSoon => 'home.menuComingSoon'.tr;
  static String get exploreComingSoon => 'home.exploreComingSoon'.tr;
  static String sectionComingSoon(String section) =>
      'home.sectionComingSoon'.trParams({'section': section});
  static String get sectionRooms => 'home.sectionRooms'.tr;
  static String get sectionDining => 'home.sectionDining'.tr;
  static String get sectionExperiences => 'home.sectionExperiences'.tr;
  static String get sectionOffers => 'home.sectionOffers'.tr;
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
  static String get heroOffersTitlePre => 'home.heroOffersTitlePre'.tr;
  static String get heroOffersTitleItalic => 'home.heroOffersTitleItalic'.tr;
  static String get heroOffersSubtitle => 'home.heroOffersSubtitle'.tr;
  static String get viewOffersLabel => 'home.viewOffersLabel'.tr;
  static String get bookNowLabel => 'home.bookNowLabel'.tr;
  static String get exploreLabel => 'home.exploreLabel'.tr;
  static String get priceFromPrefix => 'home.priceFromPrefix'.tr;
  static String get priceNightSuffix => 'home.priceNightSuffix'.tr;

  // Services
  static String get servicesTitle => 'services.title'.tr;
  static String get signInPromptTitle => 'services.signInPromptTitle'.tr;
  static String get signInPromptSubtitle => 'services.signInPromptSubtitle'.tr;
  static String get signInButtonLabel => 'services.signInButtonLabel'.tr;
  static String get createAccountButtonLabel =>
      'services.createAccountButtonLabel'.tr;
  static String get readyForNextStayTitle =>
      'services.readyForNextStayTitle'.tr;
  static String get readyForNextStaySubtitle =>
      'services.readyForNextStaySubtitle'.tr;
  static String get exploreAndBookButtonLabel =>
      'services.exploreAndBookButtonLabel'.tr;
  static String get allServicesTab => 'services.allServicesTab'.tr;
  static String activeRequestsTab(int count) =>
      'services.activeRequestsTab'.trParams({'count': '$count'});
  static String get noActiveRequestsTitle =>
      'services.noActiveRequestsTitle'.tr;
  static String get noActiveRequestsSubtitle =>
      'services.noActiveRequestsSubtitle'.tr;
  static String get browseServicesButtonLabel =>
      'services.browseServicesButtonLabel'.tr;
  static String itemSelected(String item) =>
      'services.itemSelected'.trParams({'item': item});
  static String editingRequest(String title) =>
      'services.editingRequest'.trParams({'title': title});
  static String get editRequestLabel => 'services.editRequestLabel'.tr;
  static String get cancelRequestLabel => 'services.cancelRequestLabel'.tr;
  static String get cancelReasonHint => 'services.cancelReasonHint'.tr;
  static String get requestCancelled => 'services.requestCancelled'.tr;
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

  // Stays
  static String get staysTitle => 'stays.title'.tr;
  static String get staysComingSoonTitle => 'stays.comingSoonTitle'.tr;
  static String get staysComingSoonSubtitle => 'stays.comingSoonSubtitle'.tr;

  // Book
  static String get bookTitle => 'book.title'.tr;
  static String get bookComingSoonSubtitle => 'book.comingSoonSubtitle'.tr;

  // Account
  static String get accountTitle => 'account.title'.tr;
  static String get accountComingSoonTitle => 'account.comingSoonTitle'.tr;
  static String get accountComingSoonSubtitle =>
      'account.comingSoonSubtitle'.tr;

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
  static String get reservationPhoneDestination =>
      'booking.reservationPhoneDestination'.tr;

  // Settings
  static String get settings => 'settings.settings'.tr;
  static String get language => 'settings.language'.tr;
  static String get currency => 'settings.currency'.tr;

  //File Service
  static String get errorCheckingFile => 'fileService.errorCheckingFile'.tr;
  static String get errorPickingFile => 'fileService.errorPickingFile'.tr;
  static String get errorPickingMultipleFiles =>
      'fileService.errorPickingMultipleFiles'.tr;
  static String get errorSavingFile => 'fileService.errorSavingFile'.tr;
  static String get errorReadingFile => 'fileService.errorReadingFile'.tr;
  static String get errorWritingFile => 'fileService.errorWritingFile'.tr;
  static String get errorGettingExternalStorage =>
      'fileService.errorGettingExternalStorage'.tr;
  static String get errorCheckingDirectory =>
      'fileService.errorCheckingDirectory'.tr;
  static String get errorCreatingDirectory =>
      'fileService.errorCreatingDirectory'.tr;
  static String get errorListingFiles => 'fileService.errorListingFiles'.tr;
  static String get errorDeletingFile => 'fileService.errorDeletingFile'.tr;
  static String get errorDeletingDirectory =>
      'fileService.errorDeletingDirectory'.tr;
  static String get errorGettingFileInfo =>
      'fileService.errorGettingFileInfo'.tr;
  static String get fileNotFound => 'fileService.fileNotFound'.tr;
  static String get directoryNotFound => 'fileService.directoryNotFound'.tr;
  static String get permissionDenied => 'fileService.permissionDenied'.tr;
  static String get selectFile => 'fileService.selectFile'.tr;
  static String get selectFiles => 'fileService.selectFiles'.tr;
  static String get saveFile => 'fileService.saveFile'.tr;
  static String get allFiles => 'fileService.allFiles'.tr;
  static String get documents => 'fileService.documents'.tr;
  static String get images => 'fileService.images'.tr;
  static String get videos => 'fileService.videos'.tr;
  static String get audio => 'fileService.audio'.tr;
  static String get pdf => 'fileService.pdf'.tr;
  static String get word => 'fileService.word'.tr;
  static String get excel => 'fileService.excel'.tr;

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
  static String get keyCardWaitingBody => 'checkIn.keyCardWaitingBody'.tr;
  static String get addDigitalKey => 'checkIn.addDigitalKey'.tr;
  static String get activatingDigitalKey => 'checkIn.activatingDigitalKey'.tr;
  static String get activatedDigitalKey => 'checkIn.activatedDigitalKey'.tr;
  static String get digitalKeyHint => 'checkIn.digitalKeyHint'.tr;
  static String get completeCheckIn => 'checkIn.completeCheckIn'.tr;
  static String finishStepsFirst(String step) =>
      'checkIn.finishStepsFirst'.trParams({'step': step});
  static String get stepIdentity => 'checkIn.stepIdentity'.tr;
  static String get stepSpecialRequests => 'checkIn.stepSpecialRequests'.tr;
  static String get stepContactDetails => 'checkIn.stepContactDetails'.tr;
  static String get selectArrivalTime => 'checkIn.selectArrivalTime'.tr;
  static String get arrivalTimeSubtitle => 'checkIn.arrivalTimeSubtitle'.tr;
  static String get arrivalAfternoon => 'checkIn.arrivalAfternoon'.tr;
  static String get arrivalEvening => 'checkIn.arrivalEvening'.tr;
  static String get arrivalLateNight => 'checkIn.arrivalLateNight'.tr;
  static String arrivalAfterTime(String time) =>
      'checkIn.arrivalAfterTime'.trParams({'time': time});
  static String get confirmArrivalTime => 'checkIn.confirmArrivalTime'.tr;
  static String get saveLabel => 'checkIn.save'.tr;

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
  static String get cancelled => 'general.cancelled'.tr;
  static String get inProgress => 'general.inProgress'.tr;
  static String get requested => 'general.requested'.tr;

  // Account
  static String get myProfile => 'account.myProfile'.tr;
  static String get security => 'account.security'.tr;
  static String get accountSupport => 'account.support'.tr;
  static String get helpAndSupport => 'account.helpAndSupport'.tr;
  static String get legal => 'account.legal'.tr;
  static String get signOut => 'account.signOut'.tr;
  static String get preferences => 'account.preferences'.tr;

  // Preference catalogue
  static String get bedKing => 'prefs.bedKing'.tr;
  static String get bedQueen => 'prefs.bedQueen'.tr;
  static String get bedDouble => 'prefs.bedDouble'.tr;
  static String get bedTwin => 'prefs.bedTwin'.tr;
  static String get bedSingle => 'prefs.bedSingle'.tr;
  static String get bedExtra => 'prefs.bedExtra'.tr;
  static String get pillowSoft => 'prefs.pillowSoft'.tr;
  static String get pillowFirm => 'prefs.pillowFirm'.tr;
  static String get pillowFeather => 'prefs.pillowFeather'.tr;
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
  static String get checkInCaps => 'stays.checkInCaps'.tr;
  static String get checkOutCaps => 'stays.checkOutCaps'.tr;
  static String get requestServiceCaps => 'stays.requestServiceCaps'.tr;
  static String get expressCheckoutCaps => 'stays.expressCheckoutCaps'.tr;
  static String get completedCaps => 'stays.completedCaps'.tr;
  static String get receipt => 'stays.receipt'.tr;
  static String get downloadPdfReceipt => 'stays.downloadPdfReceipt'.tr;
  static String get noKeep => 'stays.noKeep'.tr;
  static String get yesCancel => 'stays.yesCancel'.tr;
  static String get checkoutComplete => 'stays.checkoutComplete'.tr;
  static String get noActiveStayToCheckOut => 'stays.noActiveStayToCheckOut'.tr;

  // Home dashboard
  static String get expressCheckout => 'home.expressCheckout'.tr;
  static String get yourStay => 'home.yourStay'.tr;
  static String get checkoutRequested => 'home.checkoutRequested'.tr;
  static String get myBill => 'home.myBill'.tr;
  static String get homeRequest => 'home.request'.tr;
  static String get checkout => 'home.checkout'.tr;
  static String get estimatedTotal => 'home.estimatedTotal'.tr;
  static String get activeRequests => 'home.activeRequests'.tr;
  static String get newRequest => 'home.newRequest'.tr;
  static String get quickRequests => 'home.quickRequests'.tr;
  static String get askTheConcierge => 'home.askTheConcierge'.tr;

  // Dining & reviews
  static String get reserveATable => 'dining.reserveATable'.tr;
  static String get reserveATableTitle => 'dining.reserveATableTitle'.tr;
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
  static String get agentRole => 'concierge.agentRole'.tr;
  static String get quickBilling => 'concierge.quickBilling'.tr;
  static String get quickRoomIssue => 'concierge.quickRoomIssue'.tr;
  static String get quickSpecialRequest => 'concierge.quickSpecialRequest'.tr;
  static String get quickFeedback => 'concierge.quickFeedback'.tr;

  // Notifications
  static String get channelName => 'notifications.channelName'.tr;

  // API dialog

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

  static String receiptSaved(String path) =>
      'stays.receiptSaved'.trParams({'path': path});

  static String resCode(String code) =>
      'stays.resCode'.trParams({'code': code});

  static String stayRoomNumber(String number) =>
      'stays.roomNumber'.trParams({'number': number});

  static String suiteNumber(String number) =>
      'stays.suiteNumber'.trParams({'number': number});

  static String receiptSubtitle(String room, String dates) =>
      'stays.receiptSubtitle'.trParams({'room': room, 'dates': dates});

  static String checkoutConfirmBody(String target) =>
      'home.checkoutConfirmBody'.trParams({'target': target});

  static String requestConfirmation(String target) =>
      'services.requestConfirmation'.trParams({'target': target});

  static String reservationSummary(String guests, String time) =>
      'dining.reservationSummary'.trParams({'guests': guests, 'time': time});

  static String retryAfterSeconds(int count) =>
      'api.retryAfterSeconds'.trParams({'count': '$count'});

  // ── Batch 2 ──
  static String get searchRooms => 'book.searchRooms'.tr;
  static String get bankWire => 'book.bankWire'.tr;
  static String get walletTagline => 'payment.walletTagline'.tr;
  static String get upcoming => 'stays.upcoming'.tr;
  static String get noActiveStaySubtitle => 'stays.noActiveStaySubtitle'.tr;
  static String get noPastStaysSubtitle => 'stays.noPastStaysSubtitle'.tr;
  static String get beTheFirstReview => 'reviews.beTheFirst'.tr;
  static String get tabMenu => 'dining.tabMenu'.tr;
  static String get tabInfo => 'dining.tabInfo'.tr;

  /// `1 night` / `@count nights` — same two-form reasoning as [adultsCount].
  static String nightsCount(int count) => count == 1
      ? 'book.nightsCountOne'.tr
      : 'book.nightsCount'.trParams({'count': '$count'});

  static String walletUnavailable(String method) =>
      'payment.walletUnavailable'.trParams({'method': method});

  static String get googlePayTagline => 'payment.googlePayTagline'.tr;
  static String get payAtHotelTagline => 'payment.payAtHotelTagline'.tr;
  static String get statusRequested => 'services.statusRequested'.tr;
  static String get statusInProgress => 'services.statusInProgress'.tr;
  static String get statusConfirmed => 'services.statusConfirmed'.tr;
  static String get statusCompleted => 'services.statusCompleted'.tr;
  static String get statusCancelled => 'services.statusCancelled'.tr;
  static String get stayStatusCompleted => 'stays.statusCompleted'.tr;
  static String get stayStatusCancelled => 'stays.statusCancelled'.tr;
  static String get timePm => 'dining.timePm'.tr;
  static String get timeAm => 'dining.timeAm'.tr;

  static String requestConfirmationEta(String target, String eta) =>
      'services.requestConfirmationEta'.trParams({
        'target': target,
        'eta': eta,
      });

  static String get notificationChannelName => 'notifications.channelName'.tr;

  static String get signOutTitle => 'account.signOutTitle'.tr;
  static String get signOutBody => 'account.signOutBody'.tr;
  static String get cardComingSoon => 'payment.cardComingSoon'.tr;
  static String get walletComingSoon => 'payment.walletComingSoon'.tr;
  static String get googlePaySubtitle => 'payment.googlePaySubtitle'.tr;
  static String get googlePaySavedMethod => 'payment.googlePaySavedMethod'.tr;
  static String get googlePayInstant => 'payment.googlePayInstant'.tr;
  static String get checkoutStatementNote => 'home.checkoutStatementNote'.tr;
  static String get checkoutConfirmNow => 'home.checkoutConfirmNow'.tr;
  static String get loadStayFailed => 'stays.loadStayFailed'.tr;
  static String get loadReservationsFailed => 'stays.loadReservationsFailed'.tr;
  static String get loadPastFailed => 'stays.loadPastFailed'.tr;
  static String get loadMessagesFailed => 'concierge.loadMessagesFailed'.tr;

  // ── Currency ──
  static String get currencyUsd => 'currency.usd'.tr;
  static String get currencySyp => 'currency.syp'.tr;
  static String get currencyTry => 'currency.try'.tr;
  static String get currencyApproxNote => 'currency.approxNote'.tr;

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

  static String balanceDue(String amount) =>
      'stays.balanceDue'.trParams({'amount': amount});

  // -- Batch 3: named-parameter strings the literal pass missed --
  static String get notifications => 'account.notifications'.tr;
  static String get savedPayments => 'account.savedPayments'.tr;
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
  static String get menuDownloadComingSoon =>
      'dining.menuDownloadComingSoon'.tr;
  static String get signInToReview => 'reviews.signInToReview'.tr;
  static String get reviewsLoadFailed => 'reviews.loadFailed'.tr;
  static String get tryAgainShort => 'reviews.tryAgainShort'.tr;
  static String get reviewThankYou => 'reviews.thankYou'.tr;
  static String get reviewShareHint => 'reviews.shareHint'.tr;
  static String get currentBill => 'home.currentBill'.tr;
  static String get fullStatement => 'home.fullStatement'.tr;
  static String get checkoutFailed => 'home.checkoutFailed'.tr;
  static String get requestNotesHint => 'services.requestNotesHint'.tr;
  static String get promoHint => 'book.promoHint'.tr;
  static String get promoCodeFirst => 'book.promoCodeFirst'.tr;
  static String get roomLoadFailed => 'book.roomLoadFailed'.tr;
  static String get signInToBook => 'book.signInToBook'.tr;
  static String get selectRoomAndDates => 'book.selectRoomAndDates'.tr;
  static String get otpExpired => 'auth.otpExpired'.tr;
  static String get otpTooManyAttempts => 'auth.otpTooManyAttempts'.tr;
  static String get otpIncorrect => 'auth.otpIncorrect'.tr;
  static String get receiptLoadFailed => 'stays.receiptLoadFailed'.tr;
  static String get receiptDownloadFailed => 'stays.receiptDownloadFailed'.tr;
  static String get reservationCancelled => 'stays.reservationCancelled'.tr;

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
  static String get transferComingSoon => 'transfer.bookingComingSoon'.tr;

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
  static String get loyaltyTopTier => 'loyalty.topTier'.tr;
  static String get loyaltyEarnTitle => 'loyalty.earnTitle'.tr;
  static String get loyaltyEarnBody => 'loyalty.earnBody'.tr;
  static String get loyaltyEarned => 'loyalty.earned'.tr;
  static String get loyaltyRedeemed => 'loyalty.redeemed'.tr;
  static String get loyaltyStaysCounted => 'loyalty.staysCounted'.tr;
  static String get loyaltyNetChange => 'loyalty.netChange'.tr;
  static String get loyaltyActivity => 'loyalty.activity'.tr;
  static String get loyaltyNoActivity => 'loyalty.noActivity'.tr;
  static String get loyaltyNoActivityBody => 'loyalty.noActivityBody'.tr;
  static String get loyaltyRedeem => 'loyalty.redeem'.tr;
  static String get loyaltyRedeemSoon => 'loyalty.redeemSoon'.tr;

  /// `@points pts to @tier` — the caption under the tier bar. [points] is
  /// pre-formatted by `formatPoints()` so the grouping follows the guest's
  /// locale rather than being interpolated raw here.
  static String loyaltyToNextTier({
    required String points,
    required String tier,
  }) => 'loyalty.toNextTier'.trParams({'points': points, 'tier': tier});

  /// `@count Total` — the entry count on the ledger header.
  static String loyaltyEntryCount(int count) =>
      'loyalty.entryCount'.trParams({'count': '$count'});
}
