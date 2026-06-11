/**
 * O'zbek tili tarjimalari — SmartPay CRM.
 */
export const uz = {
  // ═══════════════════════════════════════════════════════
  // COMMON / UMUMIY
  // ═══════════════════════════════════════════════════════
  common: {
    loading: 'Yuklanmoqda…',
    save: 'Saqlash',
    saving: 'Saqlanmoqda…',
    cancel: 'Bekor qilish',
    confirm: 'Tasdiqlash',
    delete: "O'chirish",
    edit: 'Tahrirlash',
    add: "Qo'shish",
    close: 'Yopish',
    search: 'Qidirish',
    filter: 'Filtr',
    all: 'Barchasi',
    yes: 'Ha',
    no: "Yo'q",
    retry: 'Qayta urinish',
    back: 'Orqaga',
    next: 'Keyingi',
    actions: 'Amallar',
    status: 'Holat',
    date: 'Sana',
    period: 'Davr',
    total: 'Jami',
    download: 'Yuklab olish',
    downloading: 'Yuklanmoqda...',
    export: 'Eksport',
    import: 'Import',
    create: 'Yaratish',
    noData: "Ma'lumot yo'q",
    waiting: 'Kutilmoqda…',
    som: "so'm",
    ta: 'ta',
    pieces: 'ta',
    month: 'oy',
  },

  // ═══════════════════════════════════════════════════════
  // NAVIGATION / NAVIGATSIYA
  // ═══════════════════════════════════════════════════════
  nav: {
    dashboard: 'Dashboard',
    clients: 'Mijozlar',
    invoices: 'Hisob-faktura',
    debtors: 'Qarzdorlar',
    users: 'Foydalanuvchilar',
    auditLog: 'Audit log',
    notifications: 'Bildirishnomalar',
    logout: 'Chiqish',
  },

  // ═══════════════════════════════════════════════════════
  // ROUTE META / MARSHRUT SARLAVHALARI
  // ═══════════════════════════════════════════════════════
  routeMeta: {
    dashboard: { title: 'Boshqaruv paneli', crumb: 'Dashboard' },
    clients: { title: 'Mijozlar', crumb: 'Mijozlar' },
    invoices: { title: 'Hisob-faktura', crumb: 'Hisob-faktura' },
    debtors: { title: 'Qarzdorlar', crumb: 'Qarzdorlar' },
    notifications: { title: 'Bildirishnomalar', crumb: 'Bildirishnomalar' },
    users: { title: 'Foydalanuvchilar', crumb: 'Foydalanuvchilar' },
    auditLogs: { title: 'Audit log', crumb: 'Audit log' },
  },

  // ═══════════════════════════════════════════════════════
  // LAYOUT / SIDEBAR
  // ═══════════════════════════════════════════════════════
  layout: {
    homePage: 'Bosh sahifa',
    closeSidebar: 'Yon panelni yopish',
    openMenu: 'Menyuni ochish',
    expandPanel: 'Panelni yoyish',
    collapsePanel: "Panelni yig'ish",
    darkMode: 'Tungi rejim',
    lightMode: 'Kunduzgi rejim',
    user: 'Foydalanuvchi',
    unreadNotifications: "{count} ta o'qilmagan bildirishnoma",
  },

  // ═══════════════════════════════════════════════════════
  // AUTH / AUTENTIFIKATSIYA
  // ═══════════════════════════════════════════════════════
  auth: {
    loginTitle: 'Tizimga kirish',
    loginSubtitle: 'Hisobingizga kiring va ish boshlang',
    emailLabel: 'Email manzil',
    emailPlaceholder: 'admin@smartpay.uz',
    passwordLabel: 'Parol',
    passwordPlaceholder: '••••••••',
    rememberMe: 'Eslab qolish',
    loginButton: 'Kirish',
    loggingIn: 'Kirish...',
    showPassword: "Parolni ko'rsatish",
    emailRequired: 'Email majburiy',
    emailInvalid: "Email formati noto'g'ri",
    passwordRequired: 'Parol majburiy',
    invalidCredentials: "Email yoki parol noto'g'ri",
    rateLimited: 'Urinishlar soni oshib ketdi, {seconds} soniyadan keyin qayta urining',
    waitingCountdown: 'Kutilmoqda ({seconds}s)',
    sessionExpired: 'Sessiya muddati tugadi, qaytadan kiring',
    serverUnavailable: "Server bilan vaqtincha aloqa yo'q, keyinroq urinib ko'ring",
    copyright: '© 2026 SmartPay CRM. Barcha huquqlar himoyalangan.',
  },

  // ═══════════════════════════════════════════════════════
  // ROLES / ROLLAR
  // ═══════════════════════════════════════════════════════
  roles: {
    admin: 'Admin',
    user: 'Foydalanuvchi',
  },

  // ═══════════════════════════════════════════════════════
  // CLIENTS / MIJOZLAR
  // ═══════════════════════════════════════════════════════
  clients: {
    title: 'Mijozlar',
    newClient: 'Yangi mijoz',
    editClient: 'Mijozni tahrirlash',
    addClient: "Yangi mijoz qo'shish",
    deleteClient: "Mijozni o'chirish",
    deleteConfirm: '"{name}" (INN: {inn}) mijozini o\'chirmoqchimisiz? Bu amalni qaytarib bo\'lmaydi.',
    deleted: "Mijoz o'chirildi",
    created: 'Mijoz yaratildi',
    updated: 'Mijoz yangilandi',
    notFound: 'Mijozlar topilmadi',
    searchPlaceholder: "Mijoz nomi yoki INN bo'yicha qidirish...",
    excelImport: 'Excel import',
    excelExport: 'Excel export',
    exporting: 'Yuklanmoqda...',
    // Table headers
    col: {
      index: '#',
      name: 'Mijoz nomi',
      inn: 'INN',
      phone: 'Telefon',
      serviceDate: 'Ulangan sana',
      lastPaid: "Oxirgi to'lov",
      paymentType: "To'lov turi",
      productCount: 'Mahsulot',
      debtStatus: 'Qarz holati',
      status: 'Holat',
      actions: 'Amal',
    },
    // Payment type labels
    paymentType: {
      all: "To'lov turi: Barchasi",
      fakt: 'Fakt',
      naqt: 'Naqd',
      qarz: 'Qarz',
    },
    // Payment type descriptions
    paymentDesc: {
      fakt: 'Online',
      naqt: 'Naqd',
      qarz: 'Kreditga',
    },
    // Status
    statusFilter: {
      all: 'Holat: Barchasi',
      active: 'Faol',
      inactive: 'Nofaol',
    },
    // Debt badge
    hasDebt: 'Qarzdor',
    noDebt: "To'langan",
    // Form
    form: {
      nameLabel: 'Mijoz nomi',
      namePlaceholder: 'Kompaniya yoki F.I.O',
      innLabel: 'INN',
      innPlaceholder: '123456789',
      phoneLabel: 'Telefon',
      phonePlaceholder: '+998XXXXXXXXX',
      phone2Label: "Qo'shimcha telefon",
      phone2Placeholder: '+998XXXXXXXXX (ixtiyoriy)',
      serviceDateLabel: 'Xizmatga ulangan sana',
      productCountLabel: 'Mahsulot soni',
      lastPaidLabel: "Oxirgi to'langan sana",
      lastPaidHint: "Mijozning oxirgi to'lov qilgan oyini tanlang. Yangi mijoz bo'lsa — joriy oyni",
      paymentTypeLabel: "To'lov turi",
      statusLabel: 'Holat',
      notesLabel: 'Izoh',
      notesPlaceholder: 'Qo\'shimcha izoh...',
    },
    // Mark monthly paid
    markPaid: {
      title: "Oylik to'lovni belgilash",
      subtitle: '{name} uchun davr va usulni tanlang',
      subtitleEmpty: 'davr va usulni tanlang',
      periodLabel: 'Davr (YYYY-MM)',
      periodHint: 'Format: 2026-05 (yil-oy)',
      methodLabel: "To'lov usuli",
      confirmBtn: 'Belgilash',
      faktWarning: "Bu mijozning to'lov turi Fakt. Faktura orqali to'lov avtomatik hisoblanadi. Qo'lda belgilash kamdan-kam holatlarda zarur bo'ladi.",
      success: "{period} davri uchun {method} usulida {status}",
      conflict: "Bu davr uchun to'lov allaqachon belgilangan",
    },
    // Import
    import: {
      title: 'Excel import',
      templateHint: "Avval namuna shablonni yuklab oling va to'ldiring",
      templateBtn: 'Namuna yuklab olish',
      selectFile: 'Fayl tanlash (.xlsx)',
      checking: 'Tekshirilmoqda…',
      total: 'Jami',
      importCount: 'Import',
      duplicate: 'Dublikat',
      error: 'Xato',
      rowCol: 'Qator',
      errorCol: 'Xato',
      confirmBtn: 'Tasdiqlash',
      importing: 'Import...',
      success: '{count} ta mijoz import qilindi',
      invalidFormat: "Fayl formati noto'g'ri",
      tooLarge: 'Fayl hajmi 5 MB dan oshmasligi kerak',
    },
    innConflict: 'Bu INN bilan mijoz allaqachon mavjud',
  },

  // ═══════════════════════════════════════════════════════
  // INVOICES / HISOB-FAKTURALAR
  // ═══════════════════════════════════════════════════════
  invoices: {
    title: 'Hisob-fakturalar',
    generate: 'Yangi faktura yaratish',
    generateTitle: 'Yangi faktura yaratish',
    generateDesc: 'Tanlangan davr uchun barcha mos mijozlarni umumiy fakturaga jamlaydi',
    periodLabel: 'Davr (YYYY-MM)',
    periodHint: 'Format: 2026-05 (yil-oy)',
    generating: 'Yaratilmoqda…',
    generateBtn: 'Faktura yaratish',
    generatedSuccess: 'Faktura {number} yaratildi: {count} ta mijoz, {total} so\'m',
    conflict: 'Bu davr uchun faktura allaqachon yaratilgan',
    noClients: 'Bu davr uchun mos mijoz topilmadi',
    empty: "Hozircha faktura yo'q",
    emptyDesc: 'Yangi faktura yaratish uchun yuqoridagi tugmadan foydalaning',
    deleteTitle: "Fakturani o'chirish",
    deleteConfirm: "{number} ({period}) fakturasini o'chirmoqchimisiz? Bu amalni qaytarib bo'lmaydi.",
    deleted: "Faktura o'chirildi",
    // Card
    card: {
      period: 'Davr:',
      clientsCount: 'Mijozlar soni:',
      totalAmount: 'Umumiy summa:',
      responsible: "Mas'ul:",
      createdAt: 'Yaratilgan:',
    },
    download: 'Yuklab olish',
    col: {
      invoiceNumber: 'Faktura raqami',
      period: 'Davr',
      issueDate: 'Sana',
      totalAmount: 'Summa',
      itemsCount: 'Mijozlar',
      responsible: "Mas'ul",
    },
  },

  // ═══════════════════════════════════════════════════════
  // DEBTORS / QARZDORLAR
  // ═══════════════════════════════════════════════════════
  debtors: {
    title: 'Qarzdorlar',
    empty: 'Qarzdorlar topilmadi',
    emptyActive: "Hozircha aktiv qarzdorlar yo'q",
    emptyAll: "Hech qanday qarz yozuvi mavjud emas",
    filterActive: 'Faqat faol qarzlar',
    filterAll: "Barchasi (faol + to'langan)",
    col: {
      index: '#',
      clientName: 'Mijoz nomi',
      inn: 'INN',
      paymentType: "To'lov turi",
      duration: 'Qarz muddati',
      status: 'Holat',
      amount: 'Qarz summasi',
      actions: 'Amal',
    },
    statusActive: 'Faol qarz',
    statusPaid: "To'langan",
    payBtn: "To'lash",
    // Pay dialog
    pay: {
      title: "Qarzni to'lash",
      subtitle: '{name} — to\'lov usulini tanlang',
      amountLabel: "To'lanadigan summa",
      fullPayNote: "Qarz to'liq yopiladi (qisman to'lov qabul qilinmaydi)",
      methodLabel: "To'lov usuli",
      confirmBtn: "To'lashni tasdiqlash",
      paying: "To'lanmoqda…",
      success: "Qarz to'liq to'landi ({method})",
      conflict: "Bu qarz allaqachon to'langan",
    },
    methodFakt: 'Fakt (online)',
    methodFaktDesc: "Hisobga ko'chirish",
    methodNaqt: 'Naqt',
    methodNaqtDesc: 'Naqd pul orqali',
    monthSuffix: 'oy',
  },

  // ═══════════════════════════════════════════════════════
  // DASHBOARD / BOSHQARUV PANELI
  // ═══════════════════════════════════════════════════════
  dashboard: {
    activeClients: 'Faol mijozlar',
    activeClientsTrend: 'Faol holatdagilar',
    currentDebtors: 'Joriy oy qarzdorlar',
    debtorsTrend: 'Qarzdor mijozlar',
    unpaidDebts: "To'lanmagan qarzlar",
    unpaidDebtsTrend: 'Umumiy summa',
    monthlyInvoices: 'Oylik fakturalar',
    monthlyInvoicesTrend: 'Bu oy',
    chartTitle: "Oylik to'lovlar statistikasi",
    chartSubtitle: "Oxirgi 6 oy · so'mda",
    chartFakt: "Fakt to'lovlar",
    chartNaqt: "Naqt to'lovlar",
    donutTitle: "Mijozlar to'lov turlari",
    donutSubtitle: '{count} ta faol mijoz',
    donutCenter: 'mijoz',
    recentActions: "So'nggi amallar",
    recentActionsDesc: 'Tizimdagi oxirgi harakatlar',
    viewAll: "Barchasini ko'rish",
    noActions: "Hozircha amallar yo'q",
    months: {
      1: 'Yan', 2: 'Fev', 3: 'Mar', 4: 'Apr', 5: 'May', 6: 'Iyun',
      7: 'Iyul', 8: 'Avg', 9: 'Sen', 10: 'Okt', 11: 'Noy', 12: 'Dek',
    },
  },

  // ═══════════════════════════════════════════════════════
  // NOTIFICATIONS / BILDIRISHNOMALAR
  // ═══════════════════════════════════════════════════════
  notifications: {
    title: 'Bildirishnomalar',
    markAllRead: "Hammasini o'qildi",
    markingAllRead: 'Belgilanmoqda…',
    markRead: "O'qildi deb belgilash",
    deleteAllRead: "O'qilganlarni o'chirish",
    deletingAll: "O'chirilmoqda…",
    unreadOnly: "Faqat o'qilmaganlar",
    emptyAll: "Bildirishnomalar yo'q",
    emptyAllDesc: "Yangi bildirishnomalar shu yerda paydo bo'ladi",
    emptyUnread: "O'qilmagan bildirishnoma yo'q",
    emptyUnreadDesc: "Barcha bildirishnomalar o'qilgan",
    markedSuccess: "{count} ta bildirishnoma o'qildi deb belgilandi",
    markedEmpty: "Yangi o'qilmagan bildirishnoma yo'q",
    deletedSuccess: "{count} ta o'qilgan bildirishnoma o'chirildi",
    deletedEmpty: "O'chiradigan bildirishnoma yo'q",
    deleteBtn: "O'chirish",
  },

  // ═══════════════════════════════════════════════════════
  // USERS / FOYDALANUVCHILAR
  // ═══════════════════════════════════════════════════════
  users: {
    title: 'Foydalanuvchilar',
    newUser: 'Yangi foydalanuvchi',
    editUser: 'Foydalanuvchini tahrirlash',
    addUser: "Foydalanuvchi qo'shish",
    deleteUser: "Foydalanuvchini o'chirish",
    deleteConfirm: '"{name}" ({email}) foydalanuvchisini o\'chirmoqchimisiz?',
    deleted: "Foydalanuvchi o'chirildi",
    created: 'Foydalanuvchi yaratildi',
    updated: 'Foydalanuvchi yangilandi',
    notFound: 'Foydalanuvchilar topilmadi',
    searchEmpty: "Qidiruv natijasi bo'sh",
    noUsers: "Hozircha foydalanuvchilar yo'q",
    searchPlaceholder: "Ism yoki email bo'yicha qidirish...",
    adminOnlyWarning: 'Bu sahifa faqat Admin uchun',
    fillRequired: "Barcha majburiy maydonlarni to'ldiring",
    col: {
      index: '#',
      name: 'Ism',
      email: 'Email',
      role: 'Rol',
      lastLogin: 'Oxirgi kirish',
      status: 'Holat',
      actions: 'Amallar',
    },
    form: {
      nameLabel: 'Ism',
      namePlaceholder: 'F.I.Sh',
      emailLabel: 'Email',
      emailPlaceholder: 'user@smartpay.uz',
      passwordLabel: 'Parol',
      passwordPlaceholder: '••••••••',
      passwordHint: 'Kamida 8 belgi',
      roleLabel: 'Rol',
      roleUser: 'Foydalanuvchi',
      roleUserDesc: 'Standart kirish (R/W)',
      roleAdmin: 'Admin',
      roleAdminDesc: 'Foydalanuvchilar va audit ham',
      statusLabel: 'Holat',
      statusHint: 'Nofaol foydalanuvchi tizimga kira olmaydi',
      active: 'Faol',
      inactive: 'Nofaol',
    },
    emailConflict: "Bu email allaqachon ro'yxatdan o'tgan",
    passwordMin: "Parol kamida 8 belgidan iborat bo'lishi kerak",
    passwordRequired: 'Parol majburiy',
    // Reset password
    resetPassword: {
      title: 'Parolni qayta tiklash',
      subtitle: '{name} ({email}) uchun yangi parol yaratiladi',
      warning: "Yangi parol generatsiya qilinadi va eski parol bekor qilinadi. Yangi parol bu dialogda faqat bir marta ko'rsatiladi.",
      confirmBtn: 'Parolni qayta tiklash',
      generating: 'Yaratilmoqda…',
      success: "Yangi parol yaratildi. Foydalanuvchiga uzating va dialogni yoping — bu parol qayta ko'rsatilmaydi.",
      newPasswordLabel: 'Yangi parol',
      copyBtn: 'Nusxa olish',
      copied: 'Parol nusxalandi',
      copyError: 'Nusxalashda xatolik',
      noPassword: "Parol javobdan olib bo'lmadi",
    },
  },

  // ═══════════════════════════════════════════════════════
  // AUDIT LOG
  // ═══════════════════════════════════════════════════════
  audit: {
    title: 'Audit log',
    empty: 'Audit yozuvlari topilmadi',
    emptyFiltered: "Filtrni o'zgartiring yoki tozalang",
    emptyNoLogs: "Hozircha jurnal yozuvlari yo'q",
    clearFilters: 'Filtrni tozalash',
    col: {
      index: '#',
      action: 'Amal',
      entity: 'Obyekt',
      user: 'Foydalanuvchi',
      ip: 'IP',
      date: 'Sana',
    },
    filters: {
      entityType: 'Obyekt turi',
      user: 'Foydalanuvchi',
      dateFrom: 'Boshlanish sanasi',
      dateTo: 'Tugash sanasi',
    },
    entityTypes: {
      all: 'Barchasi',
      client: 'Mijoz',
      invoice: 'Faktura',
      debt: 'Qarz',
      user: 'Foydalanuvchi',
      notification: 'Bildirishnoma',
    },
    system: 'Tizim',
    // Action meta labels
    actions: {
      'client.created': "Mijoz qo'shildi",
      'client.updated': 'Mijoz tahrirlandi',
      'client.deleted': "Mijoz o'chirildi",
      'client.import': 'Mijozlar import qilindi',
      'client.mark_paid': "Oylik to'lov belgilandi",
      'invoice.generated': 'Faktura yaratildi',
      'invoice.deleted': "Faktura o'chirildi",
      'debt.paid': "Qarz to'landi",
      'user.created': "Foydalanuvchi qo'shildi",
      'user.updated': 'Foydalanuvchi tahrirlandi',
      'user.deleted': "Foydalanuvchi o'chirildi",
      'user.password_reset': 'Parol tiklandi',
      default: 'Amal',
    },
  },

  // ═══════════════════════════════════════════════════════
  // VALIDATION / TEKSHIRUV
  // ═══════════════════════════════════════════════════════
  validation: {
    inn: "INN 9 yoki 14 raqam bo'lishi kerak",
    phone: "Telefon +998XXXXXXXXX formatida bo'lishi kerak",
    productCount: "Mahsulot soni 1 dan 1 000 000 gacha bo'lishi kerak",
    serviceDate: "Sana bugungidan katta bo'lishi mumkin emas",
    serviceDateInvalid: "Sana noto'g'ri",
    name: "Ism 1–255 belgi bo'lishi kerak",
    notes: "Izoh 1000 belgidan oshmasligi kerak",
    period: "Davr YYYY-MM formatida bo'lishi kerak",
    email: "Email formati noto'g'ri",
    lastPaidRequired: "Oxirgi to'langan sana majburiy",
    lastPaidInvalid: "Sana noto'g'ri",
    lastPaidFuture: "Oxirgi to'langan sana joriy oydan keyin bo'lishi mumkin emas",
    fileXlsxOnly: 'Faqat .xlsx formatdagi fayllar qabul qilinadi',
    fileTooLarge: 'Fayl hajmi 5 MB dan oshmasligi kerak',
  },

  // ═══════════════════════════════════════════════════════
  // ERRORS / XATOLAR
  // ═══════════════════════════════════════════════════════
  errors: {
    network: "Tarmoq bilan aloqa yo'q. Internet ulanishingizni tekshiring",
    badRequest: "So'rov noto'g'ri",
    unauthorized: "Email yoki parol noto'g'ri",
    forbidden: "Bu amal uchun ruxsatingiz yo'q",
    conflict: 'Bu yozuv allaqachon mavjud',
    payloadTooLarge: 'Fayl hajmi 5 MB dan oshmasligi kerak',
    validationError: "Kiritilgan ma'lumotlarda xatolik bor",
    tooManyRequests: "Juda ko'p urinish. Biroz kuting",
    serverError: "Serverda xatolik yuz berdi. Qayta urinib ko'ring",
    serviceUnavailable: "Server vaqtincha ishlamayapti. Biroz kuting",
    unknown: 'Kutilmagan xatolik yuz berdi',
    pageNotAllowed: "Bu sahifaga ruxsatingiz yo'q",
  },
};
