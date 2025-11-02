import React from "react";
import { Modal, Row, Col, Typography, Image, Descriptions, List, Card, Space, Button } from "antd";
import { EyeOutlined, EditOutlined } from "@ant-design/icons";
import { Amenity, Category } from "../../../types/category/category";


const { Title, Text, Paragraph } = Typography;

interface DetailCategoryProps {
    visible: boolean;
    category: Category | null;
    onClose: () => void;
    onEdit: (category: Category) => void;
    amenities: Amenity[];
}

const DetailCategory: React.FC<DetailCategoryProps> = ({ visible, category, onClose, onEdit, amenities }) => {
    if (!category) return null;

    return (
        <Modal
            title={<Space><EyeOutlined />Chi tiết danh mục</Space>}
            open={visible}
            onCancel={onClose}
            footer={[
                <Button key="close" onClick={onClose}>Đóng</Button>,
                <Button key="edit" type="primary" icon={<EditOutlined />} onClick={() => onEdit(category)}>Chỉnh sửa</Button>
            ]}
            width={800}
        >
            <Row gutter={24}>
                <Col span={10}>
                    <Image src={category.image} alt={category.name} style={{ borderRadius: 12, width: "100%" }} />
                </Col>
                <Col span={14}>
                    <Title level={3}>{category.name}</Title>
                    <Paragraph style={{ marginTop: 16 }}>{category.description}</Paragraph>
                    <Descriptions column={1} bordered size="small" style={{ marginTop: 20 }}>
                        <Descriptions.Item label="ID">{category.id}</Descriptions.Item>
                        <Descriptions.Item label="Số homestay">{category.homestayCount}</Descriptions.Item>
                        <Descriptions.Item label="Số tiện ích">{category.amenityCount}</Descriptions.Item>
                        <Descriptions.Item label="Ngày tạo">{new Date(category.createdAt).toLocaleDateString("vi-VN")}</Descriptions.Item>
                        <Descriptions.Item label="Cập nhật">{new Date(category.updatedAt).toLocaleDateString("vi-VN")}</Descriptions.Item>
                    </Descriptions>
                    <div style={{ marginTop: 20 }}>
                        <Title level={5}>Tiện ích liên quan</Title>
                        <List
                            grid={{ gutter: 16, column: 3 }}
                            dataSource={amenities.slice(0, 6)}
                            renderItem={(item) => (
                                <List.Item>
                                    <Card size="small">
                                        <Space>
                                            <span style={{ fontSize: 24 }}>{item.icon}</span>
                                            <Text>{item.name}</Text>
                                        </Space>
                                    </Card>
                                </List.Item>
                            )}
                        />
                    </div>
                </Col>
            </Row>
        </Modal>
    );
};

export default DetailCategory;
