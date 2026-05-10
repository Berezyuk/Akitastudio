<template>
  <div class="admin-dashboard">
    <h2 class="text-2xl font-bold mb-6">Дашборд</h2>

    <!-- Карточки -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-400 text-sm">Заказов сегодня</p>
            <p class="text-3xl font-bold text-white">
              {{ stats.today_orders || 0 }}
            </p>
          </div>
          <div
            class="w-12 h-12 rounded-full bg-[#fc9303]/20 flex items-center justify-center"
          >
            <svg
              class="w-6 h-6 text-[#fc9303]"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
              ></path>
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-400 text-sm">Активных заказов</p>
            <p class="text-3xl font-bold text-white">
              {{ stats.active_orders || 0 }}
            </p>
          </div>
          <div
            class="w-12 h-12 rounded-full bg-[#fc9303]/20 flex items-center justify-center"
          >
            <svg
              class="w-6 h-6 text-[#fc9303]"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
              ></path>
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-400 text-sm">Выручка за месяц</p>
            <p class="text-3xl font-bold text-white">
              {{ formatPrice(stats.month_revenue) }}
            </p>
          </div>
          <div
            class="w-12 h-12 rounded-full bg-[#fc9303]/20 flex items-center justify-center"
          >
            <!-- Иконка рубля -->
            <svg
              class="w-6 h-6 text-[#fc9303]"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 8h6M9 12h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"
              />
              <text x="12" y="17" text-anchor="middle" fill="currentColor" font-size="10" font-weight="bold" stroke="none">₽</text>
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-400 text-sm">Всего клиентов</p>
            <p class="text-3xl font-bold text-white">
              {{ stats.total_clients || 0 }}
            </p>
          </div>
          <div
            class="w-12 h-12 rounded-full bg-[#fc9303]/20 flex items-center justify-center"
          >
            <svg
              class="w-6 h-6 text-[#fc9303]"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
              ></path>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Блок уведомлений -->
    <div class="bg-gray-800/30 rounded-xl p-6 border border-gray-700 mb-8">
      <h3 class="text-xl font-semibold mb-4 flex items-center gap-2">
        <span>🔔</span> Уведомления
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          class="flex items-center justify-between p-4 bg-gray-800 rounded-lg"
        >
          <div>
            <p class="text-sm text-gray-400">Новых заказов сегодня</p>
            <p class="text-2xl font-bold text-white">{{ newOrdersToday }}</p>
          </div>
        </div>
        <div
          class="flex items-center justify-between p-4 bg-gray-800 rounded-lg"
        >
          <div>
            <p class="text-sm text-gray-400">Заказов ожидают подтверждения</p>
            <p class="text-2xl font-bold text-white">{{ pendingOrders }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- График заказов по дням -->
    <div class="bg-gray-800/30 rounded-xl p-6 border border-gray-700 mb-8">
      <h3 class="text-xl font-semibold mb-4">
        Динамика заказов (последние 7 дней)
      </h3>
      <canvas ref="chartCanvas" class="w-full h-64"></canvas>
    </div>

    <!-- Два столбца: топ услуг и последние заказы -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- Популярные услуги с фиксированной шириной колонок -->
      <div class="bg-gray-800/30 rounded-xl p-6 border border-gray-700">
        <h3 class="text-xl font-semibold mb-4">Популярные услуги</h3>
        <div
          v-if="!popularServices.length"
          class="text-center text-gray-500 py-8"
        >
          Нет данных
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="(service, idx) in popularServices"
            :key="service.service_id"
            class="flex items-center justify-between gap-4"
          >
            <div class="flex items-center gap-3 flex-1 min-w-0">
              <span class="text-lg font-bold text-[#fc9303] flex-shrink-0"
                >{{ idx + 1 }}.</span
              >
              <span class="truncate">{{ service.name }}</span>
            </div>
            <span class="text-sm text-gray-400 whitespace-nowrap flex-shrink-0"
              >{{ pluralize(service.count) }}</span
            >
          </div>
        </div>
      </div>

      <!-- Последние заказы -->
      <div class="bg-gray-800/30 rounded-xl p-6 border border-gray-700">
        <h3 class="text-xl font-semibold mb-4">Последние заказы</h3>
        <div v-if="!recentOrders.length" class="text-center text-gray-500 py-8">
          Нет заказов
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="order in recentOrders"
            :key="order.order_id"
            class="border-b border-gray-700 pb-2 last:border-0"
          >
            <div class="flex justify-between items-start gap-4">
              <div class="flex-1 min-w-0">
                <p class="font-semibold truncate">
                  №{{ order.order_id }} – {{ order.first_name }}
                  {{ order.last_name }}
                </p>
                <p class="text-sm text-gray-400 truncate">{{ order.services || "—" }}</p>
              </div>
              <div class="text-right flex-shrink-0">
                <p class="text-[#fc9303] whitespace-nowrap">
                  {{ formatPrice(order.total_price) }}
                </p>
                <span
                  :class="[
                    'px-2 py-0.5 rounded-full text-xs whitespace-nowrap',
                    getStatusClass(order.status_name),
                  ]"
                >
                  {{ getStatusName(order.status_name) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import Chart from "chart.js/auto";

const stats = ref({});
const newOrdersToday = ref(0);
const pendingOrders = ref(0);
const recentOrders = ref([]);
const popularServices = ref([]);
const chartCanvas = ref(null);
let chartInstance = null;

// Функция для склонения (1 заказ, 2 заказа, 5 заказов)
const pluralize = (count) => {
  const remainder10 = count % 10;
  const remainder100 = count % 100;

  if (remainder100 >= 11 && remainder100 <= 19) {
    return count + " заказов";
  }
  if (remainder10 === 1) {
    return count + " заказ";
  }
  if (remainder10 >= 2 && remainder10 <= 4) {
    return count + " заказа";
  }
  return count + " заказов";
};

const fetchDashboard = async () => {
  try {
    const res = await fetch("http://localhost:8000/api/admin/dashboard", {
      credentials: "include",
    });
    const data = await res.json();
    if (data.success) {
      stats.value = data.stats || {};
      newOrdersToday.value = data.stats?.new_orders_today ?? 0;
      pendingOrders.value = data.stats?.pending_orders ?? 0;
      recentOrders.value = data.recent_orders || [];
      popularServices.value = data.popular_services || [];
      if (data.chart_data) renderChart(data.chart_data);
    }
  } catch (err) {
    console.error(err);
  }
};

const renderChart = (chartData) => {
  if (!chartCanvas.value) return;
  if (chartInstance) chartInstance.destroy();

  const ctx = chartCanvas.value.getContext("2d");
  chartInstance = new Chart(ctx, {
    type: "line",
    data: {
      labels: chartData.labels,
      datasets: [
        {
          label: "Количество заказов",
          data: chartData.values,
          borderColor: "#fc9303",
          backgroundColor: "rgba(252, 147, 3, 0.1)",
          tension: 0.3,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: { labels: { color: "#fff" } },
      },
      scales: {
        y: { ticks: { color: "#fff" }, grid: { color: "#4d4d4d" } },
        x: { ticks: { color: "#fff" }, grid: { color: "#4d4d4d" } },
      },
    },
  });
};

const formatPrice = (price) => {
  if (!price && price !== 0) return "0 ₽";
  return price.toLocaleString() + " ₽";
};

const getStatusName = (statusName) => {
  const names = {
    pending: "Ожидание",
    confirmed: "Подтверждён",
    in_progress: "В работе",
    completed: "Выполнен",
    cancelled: "Отменён",
  };
  return names[statusName] || statusName;
};

const getStatusClass = (statusName) => {
  const classes = {
    pending: "bg-yellow-500/20 text-yellow-400",
    confirmed: "bg-blue-500/20 text-blue-400",
    in_progress: "bg-orange-500/20 text-orange-400",
    completed: "bg-green-500/20 text-green-400",
    cancelled: "bg-red-500/20 text-red-400",
  };
  return classes[statusName] || "bg-gray-500/20 text-gray-400";
};

onMounted(() => {
  fetchDashboard();
});
</script>