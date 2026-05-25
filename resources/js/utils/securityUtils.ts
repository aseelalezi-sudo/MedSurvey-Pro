/**
 * Masks sensitive information like phone numbers for privacy
 * Example: 777123456 -> 777****56
 */
export const maskPhoneNumber = (phone: string): string => {
  if (!phone || phone.length < 5) return phone;
  const start = phone.substring(0, 3);
  const end = phone.substring(phone.length - 2);
  return `${start}****${end}`;
};
