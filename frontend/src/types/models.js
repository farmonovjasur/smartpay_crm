/**
 * SmartPay CRM — JSDoc tip modellari.
 * Backend JSON shakllariga (snake_case) aynan mos.
 * Pul qiymatlari decimal-string sifatida saqlanadi (aniqlik yo'qolmasligi uchun).
 *
 * @module types/models
 */

/**
 * @typedef {'admin'|'user'} Role
 * @typedef {'fakt'|'naqt'|'qarz'} PaymentType
 * @typedef {'faol'|'nofaol'} ClientStatus
 * @typedef {'active'|'paid'} DebtStatus
 * @typedef {'fakt'|'naqt'} PayMethod
 */

/**
 * @typedef {Object} User
 * @property {number} id
 * @property {string} name
 * @property {string} email
 * @property {Role} role
 * @property {boolean} [is_active]
 * @property {string|null} [last_login_at]
 */

/**
 * @typedef {Object} ActiveDebtSummary
 * @property {number} id
 * @property {string} amount
 * @property {number} months_overdue
 * @property {string} first_overdue_period
 */

/**
 * @typedef {Object} Client
 * @property {number} id
 * @property {string} inn
 * @property {string} name
 * @property {string} phone
 * @property {string} service_date
 * @property {PaymentType} payment_type
 * @property {number} product_count
 * @property {ClientStatus} status
 * @property {string} [notes]
 * @property {string} monthly_amount
 * @property {boolean} has_active_debt
 * @property {ActiveDebtSummary|null} active_debt
 * @property {string} [created_at]
 */

/**
 * @typedef {Object} InvoiceItem
 * @property {number} id
 * @property {number} client_id
 * @property {string} client_name
 * @property {string} client_inn
 * @property {number} quantity
 * @property {string} unit_price
 * @property {string} total_price
 * @property {boolean} is_carried_debt
 */

/**
 * @typedef {Object} Invoice
 * @property {number} id
 * @property {string} invoice_number
 * @property {string} period
 * @property {string} issue_date
 * @property {string} total_amount
 * @property {number} items_count
 * @property {string} responsible_name
 * @property {string} [unit_price_snapshot]
 * @property {string} [product_name_snapshot]
 * @property {InvoiceItem[]} [items]
 */

/**
 * @typedef {Object} Debt
 * @property {number} id
 * @property {number} client_id
 * @property {string} client_name
 * @property {string} client_inn
 * @property {string} amount
 * @property {string} monthly_amount
 * @property {number} months_overdue
 * @property {PaymentType} payment_type_snapshot
 * @property {DebtStatus} status
 * @property {string} first_overdue_period
 * @property {string} last_overdue_period
 * @property {string|null} [paid_at]
 * @property {PayMethod|null} [paid_method]
 */

/**
 * @typedef {Object} ClientMonthlyStatus
 * @property {number} id
 * @property {number} client_id
 * @property {string} period
 * @property {'paid'|'unpaid'|'skipped'} payment_status
 * @property {PayMethod|null} payment_method
 * @property {string|null} [paid_at]
 */

/**
 * @typedef {Object} Notification
 * @property {number} id
 * @property {string} type
 * @property {string} title
 * @property {string} message
 * @property {boolean} is_read
 * @property {string} created_at
 */

/**
 * @typedef {Object} AuditLog
 * @property {number} id
 * @property {number|null} user_id
 * @property {string} action
 * @property {string} entity_type
 * @property {string|null} entity_id
 * @property {Object|null} [details]
 * @property {string|null} ip
 * @property {string} created_at
 */

/**
 * @typedef {Object} MonthlyChartPoint
 * @property {string} period
 * @property {number} fakt
 * @property {number} naqt
 */

/**
 * @typedef {Object} DashboardStats
 * @property {number} activeClients
 * @property {number} debtorsCount
 * @property {string} totalDebt
 * @property {number} invoicesThisMonth
 * @property {MonthlyChartPoint[]} monthlyChart
 * @property {{fakt:number, naqt:number, qarz:number}} byPaymentType
 * @property {{fromFakt:number, fromNaqt:number, fromQarz:number}} debtorsBreakdown
 */

/**
 * @typedef {Object} ImportErrorRow
 * @property {number} row
 * @property {string[]} errors
 */

/**
 * @typedef {Object} ImportDuplicateRow
 * @property {number} row
 * @property {string} inn
 */

/**
 * @typedef {Object} ImportResult
 * @property {number} totalRows
 * @property {number} importedCount
 * @property {ImportDuplicateRow[]} duplicateRows
 * @property {ImportErrorRow[]} errorRows
 * @property {Client[]} [preview]
 */

/**
 * @template T
 * @typedef {Object} Paginated
 * @property {T[]} data
 * @property {number} total
 * @property {number} page
 * @property {number} pageSize
 */

/**
 * @typedef {Object} NormalizedError
 * @property {number|null} status
 * @property {boolean} isNetwork
 * @property {Object|null} fieldErrors
 * @property {number|null} retryAfter
 * @property {string} message
 */

export {};
