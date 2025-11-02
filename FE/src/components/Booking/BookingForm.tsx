import { Form, DatePicker, Select, Button, Space } from "antd";
import dayjs from "dayjs";

export default function BookingForm() {
    const adults = [2, 3, 4, 5, 6].map(n => ({ value: n, label: n }));
    const children = [1, 2, 3, 4, 5].map(n => ({ value: n, label: n }));

    return (
        <Form layout="inline">
            <Space wrap>
                <Form.Item label="Check In">
                    <DatePicker defaultValue={dayjs()} />
                </Form.Item>
                <Form.Item label="Check Out">
                    <DatePicker defaultValue={dayjs().add(1, "day")} />
                </Form.Item>
                <Form.Item label="Adults">
                    <Select style={{ width: 100 }} defaultValue={2} options={adults} />
                </Form.Item>
                <Form.Item label="Children">
                    <Select style={{ width: 100 }} defaultValue={1} options={children} />
                </Form.Item>
                <Button type="primary" style={{ height: 38 }}>Book Now</Button>
            </Space>
        </Form>
    );
}
