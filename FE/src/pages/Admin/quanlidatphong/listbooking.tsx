import React, { useState } from "react";
import {
  Table,
  Card,
  Row,
  Col,
  Statistic,
  Space,
  Input,
  Select,
  Badge,
  Button,
  DatePicker,
  message,
  Modal,
  Tag,
  Tooltip,
} from "antd";
import {
  EyeOutlined,
  CalendarOutlined,
  CheckCircleOutlined,
  ClockCircleOutlined,
  CloseCircleOutlined,
  ExclamationCircleOutlined,
  DollarOutlined,
  PhoneOutlined,
  HomeOutlined,
  UserOutlined,
  TeamOutlined,
  FilterOutlined,
  FileExcelOutlined,
  FilePdfOutlined,
} from "@ant-design/icons";
import dayjs, { Dayjs } from "dayjs";
import { Booking, BookingStatus } from "../../../types/booking/booking";

const { Search } = Input;
const { Option } = Select;
const { RangePicker } = DatePicker;

const mockBookings: Booking[] = [
  {
    id: "BK001",
    customerName: "Nguyễn Văn A",
    customerPhone: "0901234567",
    customerEmail: "nguyenvana@email.com",
    homestayName: "Homestay Đà Lạt View Núi",
    homestayId: 1,
    checkIn: "2025-11-05",
    checkOut: "2025-11-08",
    nights: 3,
    guests: 2,
    totalPrice: 3600000,
    status: "Đã xác nhận",
    createdAt: "2025-10-25",
    staff: "Phạm Thị B",
    paymentMethod: "Chuyển khoản",
  },
  {
    id: "BK002",
    customerName: "Trần Thị C",
    customerPhone: "0912345678",
    customerEmail: "tranthic@email.com",
    homestayName: "Villa Biển Nha Trang",
    homestayId: 2,
    checkIn: "2025-11-10",
    checkOut: "2025-11-15",
    nights: 5,
    guests: 6,
    totalPrice: 17500000,
    status: "Đã thanh toán",
    createdAt: "2025-10-26",
    staff: "Lê Văn D",
    paymentMethod: "Thẻ tín dụng",
  },
  {
    id: "BK003",
    customerName: "Lê Văn E",
    customerPhone: "0923456789",
    customerEmail: "levane@email.com",
    homestayName: "Căn hộ Hồ Tây",
    homestayId: 3,
    checkIn: "2025-11-01",
    checkOut: "2025-11-03",
    nights: 2,
    guests: 4,
    totalPrice: 3600000,
    status: "Đang chờ",
    createdAt: "2025-10-28",
    staff: "Nguyễn Thị F",
    paymentMethod: "Tiền mặt",
  },
  {
    id: "BK004",
    customerName: "Phạm Thị G",
    customerPhone: "0934567890",
    customerEmail: "phamthig@email.com",
    homestayName: "Phòng Studio Quận 1",
    homestayId: 4,
    checkIn: "2025-10-20",
    checkOut: "2025-10-22",
    nights: 2,
    guests: 2,
    totalPrice: 1800000,
    status: "Hoàn thành",
    createdAt: "2025-10-15",
    staff: "Hoàng Văn H",
    paymentMethod: "Chuyển khoản",
  },
  {
    id: "BK005",
    customerName: "Hoàng Văn I",
    customerPhone: "0945678901",
    customerEmail: "hoangvani@email.com",
    homestayName: "Homestay Hội An Cổ Kính",
    homestayId: 5,
    checkIn: "2025-11-20",
    checkOut: "2025-11-23",
    nights: 3,
    guests: 5,
    totalPrice: 4500000,
    status: "Đã hủy",
    createdAt: "2025-10-22",
    staff: "Trần Văn J",
    paymentMethod: "Chuyển khoản",
    cancellationReason: "Khách thay đổi lịch trình",
  },
];

const ListBooking: React.FC = () => {
  const [bookings, setBookings] = useState<Booking[]>(mockBookings);
  const [filteredBookings, setFilteredBookings] =
    useState<Booking[]>(mockBookings);
  const [searchText, setSearchText] = useState("");
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [dateRange, setDateRange] = useState<[Dayjs, Dayjs] | null>(null);
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
  const [selectedBooking, setSelectedBooking] = useState<Booking | null>(null);

  const applyFilters = (
    search: string,
    status: string,
    dates: [Dayjs, Dayjs] | null
  ) => {
    let filtered = bookings;
    if (search) {
      const searchLower = search.toLowerCase();
      filtered = filtered.filter(
        (b) =>
          b.id.toLowerCase().includes(searchLower) ||
          b.customerName.toLowerCase().includes(searchLower) ||
          b.customerPhone.includes(search) ||
          b.homestayName.toLowerCase().includes(searchLower)
      );
    }
    if (status !== "all") {
      filtered = filtered.filter((b) => b.status === status);
    }
    if (dates) {
      filtered = filtered.filter((b) => {
        const checkIn = dayjs(b.checkIn);
        return checkIn.isAfter(dates[0]) && checkIn.isBefore(dates[1]);
      });
    }
    setFilteredBookings(filtered);
  };

  const handleSearch = (value: string) => {
    setSearchText(value);
    applyFilters(value, statusFilter, dateRange);
  };
  const handleStatusFilterChange = (value: string) => {
    setStatusFilter(value);
    applyFilters(searchText, value, dateRange);
  };
  const handleDateRangeChange = (dates: any) => {
    setDateRange(dates);
    applyFilters(searchText, statusFilter, dates);
  };
  const handleViewDetail = (booking: Booking) => {
    setSelectedBooking(booking);
    setIsDetailModalOpen(true);
  };
  const handleExportExcel = () => {
    message.success("Đang xuất Excel...");
  };
  const handleExportPDF = () => {
    message.success("Đang xuất PDF...");
  };

  const getStatusConfig = (status: BookingStatus) => {
    const configs: Record<
      BookingStatus,
      { color: string; icon: React.ReactNode }
    > = {
      "Đang chờ": { color: "default", icon: <ClockCircleOutlined /> },
      "Đã xác nhận": {
        color: "processing",
        icon: <ExclamationCircleOutlined />,
      },
      "Đã thanh toán": { color: "success", icon: <CheckCircleOutlined /> },
      "Đã hủy": { color: "error", icon: <CloseCircleOutlined /> },
      "Hoàn thành": { color: "success", icon: <CheckCircleOutlined /> },
    };
    return configs[status];
  };

  const columns = [
    {
      title: "Mã đặt phòng",
      dataIndex: "id",
      key: "id",
      render: (id: string) => (
        <span style={{ fontWeight: 600, color: "#1890ff" }}>{id}</span>
      ),
    },
    {
      title: "Khách hàng",
      key: "customer",
      render: (_: any, record: Booking) => (
        <div>
          <div>{record.customerName}</div>
          <div>
            <PhoneOutlined /> {record.customerPhone}
          </div>
        </div>
      ),
    },
    {
      title: "Homestay",
      dataIndex: "homestayName",
      key: "homestayName",
      render: (name: string) => (
        <Space>
          <HomeOutlined />
          {name}
        </Space>
      ),
    },
    {
      title: "Check-in",
      dataIndex: "checkIn",
      key: "checkIn",
      sorter: (a: Booking, b: Booking) =>
        dayjs(a.checkIn).unix() - dayjs(b.checkIn).unix(),
      render: (date: string) => dayjs(date).format("DD/MM/YYYY"),
    },
    {
      title: "Check-out",
      dataIndex: "checkOut",
      key: "checkOut",
      render: (date: string) => dayjs(date).format("DD/MM/YYYY"),
    },
    {
      title: "Trạng thái",
      dataIndex: "status",
      key: "status",
      render: (status: BookingStatus) => {
        const cfg = getStatusConfig(status);
        return (
          <Tag color={cfg.color} icon={cfg.icon}>
            {status}
          </Tag>
        );
      },
    },
    {
      title: "Hành động",
      key: "action",
      render: (_: any, record: Booking) => (
        <Space>
          <Tooltip title="Xem chi tiết">
            <Button
              icon={<EyeOutlined />}
              onClick={() => handleViewDetail(record)}
            />
          </Tooltip>
        </Space>
      ),
    },
  ];

  return (
    <div style={{ padding: 24 }}>
      <Space style={{ marginBottom: 16 }}>
        <Search
          placeholder="Tìm kiếm..."
          allowClear
          onSearch={handleSearch}
          onChange={(e) => handleSearch(e.target.value)}
          style={{ width: 300 }}
        />
        <Select
          value={statusFilter}
          style={{ width: 180 }}
          onChange={handleStatusFilterChange}
        >
          <Option value="all">
            <Badge status="default" text="Tất cả trạng thái" />
          </Option>
          <Option value="Đang chờ">
            <Badge status="warning" text="Đang chờ" />
          </Option>
          <Option value="Đã xác nhận">
            <Badge status="processing" text="Đã xác nhận" />
          </Option>
          <Option value="Đã thanh toán">
            <Badge status="success" text="Đã thanh toán" />
          </Option>
          <Option value="Hoàn thành">
            <Badge status="success" text="Hoàn thành" />
          </Option>
          <Option value="Đã hủy">
            <Badge status="error" text="Đã hủy" />
          </Option>
        </Select>
        <RangePicker onChange={handleDateRangeChange} />
        <Button icon={<FileExcelOutlined />} onClick={handleExportExcel}>
          Xuất Excel
        </Button>
        <Button icon={<FilePdfOutlined />} onClick={handleExportPDF}>
          Xuất PDF
        </Button>
      </Space>
      <Table columns={columns} dataSource={filteredBookings} rowKey="id" />

      <Modal
        visible={isDetailModalOpen}
        onCancel={() => setIsDetailModalOpen(false)}
        footer={null}
        width={700}
      >
        {selectedBooking && (
          <div>
            <p>Mã đặt phòng: {selectedBooking.id}</p>
            <p>Khách hàng: {selectedBooking.customerName}</p>
            <p>Homestay: {selectedBooking.homestayName}</p>
            <p>
              Trạng thái:{" "}
              <Tag color={getStatusConfig(selectedBooking.status).color}>
                {selectedBooking.status}
              </Tag>
            </p>
          </div>
        )}
      </Modal>
    </div>
  );
};

export default ListBooking;
