/**
 * Decimal-string pul qiymatini ming ajratgich bilan formatlaydi.
 * JS Number orqali yaxlitlamasdan, aniqlikni saqlaydi.
 * @param {string} decimalString — masalan "1500000.00"
 * @returns {string} — masalan "1 500 000.00"
 */
export function formatMoney(decimalString) {
  if (!decimalString && decimalString !== '0') return '0';
  const str = String(decimalString);
  const [intPart, fracPart] = str.split('.');
  const sign = intPart.startsWith('-') ? '-' : '';
  const digits = sign ? intPart.slice(1) : intPart;
  const grouped = digits.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  const result = fracPart !== undefined ? `${grouped}.${fracPart}` : grouped;
  return sign + result;
}
