// Единый источник цвета бейджа статуса заказа по status_id.
// Авторитет — таблица order_statuses (docker/postgres/init.sql):
//   1 Новый, 2 В работе, 3 Готово, 4 Выдан, 5 Отменён.
//
// Раньше карта цветов была скопирована в 4 вьюхах. В AdminOrders цвета 3/4 были
// перепутаны местами относительно ЛК клиента — один заказ выглядел по-разному у
// админа и у клиента. Плюс AdminClients/AdminDashboard ключевали карту по
// английским статус-именам (pending/confirmed/...), которых в БД нет, — бейдж
// всегда падал в серый fallback. Теперь один источник, ключ — status_id.
const STATUS_COLORS = {
  1: 'bg-yellow-500/20 text-yellow-400', // Новый
  2: 'bg-orange-500/20 text-orange-400', // В работе
  3: 'bg-green-500/20 text-green-400',   // Готово
  4: 'bg-blue-500/20 text-blue-400',     // Выдан
  5: 'bg-red-500/20 text-red-400',       // Отменён
}

export function statusColor(statusId) {
  return STATUS_COLORS[statusId] || 'bg-gray-500/20 text-gray-400'
}
