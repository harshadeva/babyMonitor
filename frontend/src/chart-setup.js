import {
  BarElement,
  CategoryScale,
  Chart,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
} from 'chart.js'

Chart.register(BarElement, CategoryScale, LinearScale, LineElement, PointElement, Legend, Tooltip)
