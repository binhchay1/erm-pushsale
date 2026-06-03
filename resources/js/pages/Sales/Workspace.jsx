import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import { 
    Phone, 
    PhoneCall, 
    PhoneOff, 
    User, 
    Clock, 
    Save, 
    Calendar, 
    DollarSign, 
    MapPin, 
    ShoppingBag, 
    CheckCircle2, 
    Volume2, 
    Mic, 
    MicOff, 
    AlertCircle, 
    Notebook,
    FileText,
    Truck,
    RefreshCw
} from 'lucide-react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { OperationOrderTable } from '@/components/operations/OperationOrderTable';
import { StatusTabs } from '@/components/operations/StatusTabs';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/format';

export default function Workspace({ filters, filterOptions, filterFields, report }) {
    const [selectedOrder, setSelectedOrder] = useState(null);
    
    // VoIP Dialer Simulation States
    const [callState, setCallState] = useState('idle'); // idle | dialing | connected | ended
    const [callDuration, setCallDuration] = useState(0);
    const [isMuted, setIsMuted] = useState(false);
    const [isSpeakerOn, setIsSpeakerOn] = useState(true);
    const callTimer = useRef(null);

    // Operational Form States
    const [callResult, setCallResult] = useState('');
    const [notes, setNotes] = useState('');
    const [callbackTime, setCallbackTime] = useState('');
    const [shippingAddress, setShippingAddress] = useState('');
    const [shippingProvider, setShippingProvider] = useState('ghtk');
    const [shippingMethod, setShippingMethod] = useState('road');
    const [amountToCollect, setAmountToCollect] = useState(0);
    const [processing, setProcessing] = useState(false);

    // Sync form values when selectedOrder changes
    useEffect(() => {
        if (selectedOrder) {
            setCallResult(selectedOrder.operationResult || '');
            setNotes(selectedOrder.customerNote || '');
            setShippingAddress(selectedOrder.shippingAddress || '');
            setAmountToCollect(selectedOrder.total || 0);
            setCallState('idle');
            setCallDuration(0);
            if (callTimer.current) clearInterval(callTimer.current);
        }
    }, [selectedOrder]);

    // Timer logic for connected calls
    useEffect(() => {
        if (callState === 'connected') {
            callTimer.current = setInterval(() => {
                setCallDuration(prev => prev + 1);
            }, 1000);
        } else {
            if (callTimer.current) {
                clearInterval(callTimer.current);
            }
        }
        return () => {
            if (callTimer.current) clearInterval(callTimer.current);
        };
    }, [callState]);

    const formatDuration = (sec) => {
        const m = Math.floor(sec / 60).toString().padStart(2, '0');
        const s = (sec % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    };

    // Softphone simulated actions
    const handleStartCall = () => {
        setCallState('dialing');
        toast.info(`Đang gọi tới ${selectedOrder.customerName}...`);
        setTimeout(() => {
            setCallState('connected');
            setCallDuration(0);
            toast.success('Cuộc gọi đã kết nối');
        }, 1500);
    };

    const handleEndCall = () => {
        setCallState('ended');
        toast.warning('Cuộc gọi kết thúc');
    };

    const applyQuickTemplate = (text) => {
        setNotes(prev => prev ? `${prev} - ${text}` : text);
    };

    // Fast order save & chốt đơn
    const handleQuickUpdate = (e) => {
        e.preventDefault();
        if (!selectedOrder) return;

        setProcessing(true);
        router.post(
            `/sales/orders/${selectedOrder.id}/close`,
            {
                shipping_address: shippingAddress,
                shipping_provider: shippingProvider,
                shipping_method: shippingMethod,
                amount_to_collect: Number(amountToCollect),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Đã cập nhật tác nghiệp & Chốt đơn ${selectedOrder.orderCode}`);
                    setSelectedOrder(null);
                },
                onError: (errors) => {
                    toast.error(errors.order ?? 'Không chốt được đơn. Vui lòng kiểm tra lại thông tin.');
                },
                onFinish: () => setProcessing(false),
            }
        );
    };

    // Calculate quick stats from row data
    const totalLeads = report.rows?.length || 0;
    const closedCount = report.rows?.filter(r => r.closedAt).length || 0;
    const estRevenue = report.rows?.reduce((sum, r) => sum + (r.closedAt ? Number(r.total) : 0), 0) || 0;

    return (
        <AppLayout>
            <Head title="Sale tác nghiệp - Dialer Workspace" />

            <div className="space-y-6">
                {/* Header Section */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                            <span>Sale tác nghiệp</span>
                            <span className="text-xs font-normal bg-primary/10 text-primary px-2.5 py-0.5 rounded-full">Dialer Mode v2</span>
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Hàng đợi cuộc gọi thông minh, Softphone tích hợp và công cụ chốt đơn siêu tốc.
                        </p>
                    </div>
                </div>

                {/* Dashboard Quick Stats Bar */}
                <div className="grid gap-3 sm:grid-cols-3">
                    <Card className="shadow-sm border-slate-100 bg-gradient-to-br from-white to-blue-50/20">
                        <CardContent className="p-4 flex items-center gap-4">
                            <div className="p-3 bg-blue-50 text-blue-600 rounded-lg">
                                <User className="size-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-slate-500">Hàng đợi lead hôm nay</p>
                                <h3 className="text-xl font-bold text-slate-900">{totalLeads} lead</h3>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="shadow-sm border-slate-100 bg-gradient-to-br from-white to-emerald-50/20">
                        <CardContent className="p-4 flex items-center gap-4">
                            <div className="p-3 bg-emerald-50 text-emerald-600 rounded-lg">
                                <CheckCircle2 className="size-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-slate-500">Đơn đã chốt</p>
                                <h3 className="text-xl font-bold text-slate-900">{closedCount} đơn</h3>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="shadow-sm border-slate-100 bg-gradient-to-br from-white to-violet-50/20">
                        <CardContent className="p-4 flex items-center gap-4">
                            <div className="p-3 bg-violet-50 text-violet-600 rounded-lg">
                                <DollarSign className="size-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-slate-500">Doanh số tạm tính</p>
                                <h3 className="text-xl font-bold text-slate-900">{formatCurrency(estRevenue)}</h3>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Content Pane */}
                <div className="grid gap-6 xl:grid-cols-12">
                    {/* Left Pane: Lead Queue (65% width) */}
                    <div className="xl:col-span-8 space-y-4">
                        <ReportFilterBar
                            routeUrl="/sales/workspace"
                            filters={filters}
                            filterOptions={filterOptions}
                            filterFields={filterFields}
                        />

                        <StatusTabs
                            routeUrl="/sales/workspace"
                            filters={filters}
                            tabs={report.statusTabs}
                        />

                        <div className="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                            <div className="p-4 border-b border-border bg-slate-50/50 flex items-center justify-between">
                                <div className="text-sm font-semibold">Danh sách lead cần xử lý</div>
                                <span className="text-xs text-muted-foreground">Click vào dòng bất kỳ để mở bảng tác nghiệp và Softphone</span>
                            </div>
                            <OperationOrderTable 
                                rows={report.rows} 
                                enableCloseOrder={false} 
                                selectedId={selectedOrder?.id}
                                onRowClick={(row) => setSelectedOrder(row)}
                            />
                        </div>
                    </div>

                    {/* Right Pane: TeleOps Panel & VoIP softphone (35% width) */}
                    <div className="xl:col-span-4">
                        {selectedOrder ? (
                            <div className="space-y-4 sticky top-6">
                                {/* VoIP Softphone Panel */}
                                <Card className="border-primary/20 shadow-md">
                                    <CardHeader className="bg-gradient-to-r from-primary/5 to-primary/10 border-b border-border pb-3.5">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <div className="size-2 rounded-full bg-emerald-500 animate-pulse" />
                                                <span className="text-xs font-semibold text-primary uppercase tracking-wider">Tổng đài VoIP</span>
                                            </div>
                                            <span className="text-xs font-mono bg-white px-2 py-0.5 rounded shadow-sm border text-muted-foreground">{selectedOrder.orderCode}</span>
                                        </div>
                                        <div className="mt-3">
                                            <h3 className="font-bold text-lg text-slate-800">{selectedOrder.customerName}</h3>
                                            <p className="text-sm font-semibold font-mono text-slate-600 flex items-center gap-1.5">
                                                <span>{selectedOrder.customerPhone}</span>
                                                {selectedOrder.phoneCarrier && (
                                                    <span className="text-[10px] px-1.5 py-0.2 bg-slate-200 text-slate-700 rounded-sm font-sans uppercase">
                                                        {selectedOrder.phoneCarrier}
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                    </CardHeader>
                                    
                                    <CardContent className="p-4 space-y-4">
                                        {/* Softphone status view */}
                                        <div className="bg-slate-900 text-white p-4 rounded-xl flex flex-col items-center justify-center min-h-[110px] text-center relative overflow-hidden">
                                            <div className="absolute inset-0 bg-radial-gradient from-primary/20 to-transparent opacity-50" />
                                            {callState === 'idle' && (
                                                <div className="space-y-2 z-10">
                                                    <p className="text-xs text-slate-400">Sẵn sàng thực hiện cuộc gọi</p>
                                                    <Button 
                                                        size="sm" 
                                                        className="bg-emerald-600 hover:bg-emerald-500 text-white rounded-full px-5 gap-1.5"
                                                        onClick={handleStartCall}
                                                    >
                                                        <PhoneCall className="size-4" />
                                                        Bắt đầu gọi
                                                    </Button>
                                                </div>
                                            )}
                                            {callState === 'dialing' && (
                                                <div className="space-y-2 z-10 animate-pulse">
                                                    <p className="text-xs text-slate-300">Đang quay số...</p>
                                                    <p className="text-lg font-mono tracking-widest">{selectedOrder.customerPhone}</p>
                                                    <Button size="icon" variant="destructive" className="rounded-full size-8" onClick={() => setCallState('idle')}>
                                                        <PhoneOff className="size-4" />
                                                    </Button>
                                                </div>
                                            )}
                                            {callState === 'connected' && (
                                                <div className="space-y-2 z-10 w-full">
                                                    <div className="flex items-center justify-center gap-2">
                                                        <span className="size-2 rounded-full bg-emerald-500 animate-ping" />
                                                        <p className="text-xs text-emerald-400 font-semibold tracking-wider uppercase">Đang kết nối</p>
                                                    </div>
                                                    <p className="text-2xl font-bold font-mono tracking-wider">{formatDuration(callDuration)}</p>
                                                    
                                                    {/* Call options */}
                                                    <div className="flex items-center justify-center gap-4 mt-2">
                                                        <Button 
                                                            size="icon" 
                                                            variant="outline" 
                                                            className={`rounded-full size-8 border-slate-700 hover:bg-slate-800 ${isMuted ? 'bg-amber-500 hover:bg-amber-400 text-slate-950 border-none' : 'text-slate-200'}`}
                                                            onClick={() => setIsMuted(!isMuted)}
                                                            title={isMuted ? 'Bật mic' : 'Tắt tiếng mic'}
                                                        >
                                                            <MicOff className="size-4" />
                                                        </Button>
                                                        <Button 
                                                            size="icon" 
                                                            variant="destructive" 
                                                            className="rounded-full size-10" 
                                                            onClick={handleEndCall}
                                                            title="Treo máy"
                                                        >
                                                            <PhoneOff className="size-5" />
                                                        </Button>
                                                        <Button 
                                                            size="icon" 
                                                            variant="outline" 
                                                            className={`rounded-full size-8 border-slate-700 hover:bg-slate-800 ${isSpeakerOn ? 'bg-blue-600 hover:bg-blue-500 text-white border-none' : 'text-slate-200'}`}
                                                            onClick={() => setIsSpeakerOn(!isSpeakerOn)}
                                                            title="Loa ngoài"
                                                        >
                                                            <Volume2 className="size-4" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            )}
                                            {callState === 'ended' && (
                                                <div className="space-y-2 z-10">
                                                    <p className="text-xs text-slate-400">Cuộc gọi kết thúc</p>
                                                    <p className="text-sm font-semibold font-mono text-slate-300">Thời lượng: {formatDuration(callDuration)}</p>
                                                    <Button 
                                                        size="sm" 
                                                        variant="secondary" 
                                                        className="px-4 py-1 text-xs" 
                                                        onClick={handleStartCall}
                                                    >
                                                        Gọi lại
                                                    </Button>
                                                </div>
                                            )}
                                        </div>

                                        {/* Actionable Form */}
                                        <form onSubmit={handleQuickUpdate} className="space-y-4">
                                            {/* Trạng thái/Kết quả gọi */}
                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-semibold text-slate-700">Kết quả cuộc gọi</Label>
                                                <div className="grid grid-cols-2 gap-1.5">
                                                    {[
                                                        { value: 'no_answer', label: 'Không nghe máy' },
                                                        { value: 'busy', label: 'Hẹn gọi lại' },
                                                        { value: 'wrong_number', label: 'Sai số' },
                                                        { value: 'not_interested', label: 'Không chốt' },
                                                    ].map((opt) => (
                                                        <Button
                                                            key={opt.value}
                                                            type="button"
                                                            size="sm"
                                                            variant={callResult === opt.value ? 'default' : 'outline'}
                                                            className="text-xs py-1 px-2.5 h-auto justify-start font-normal"
                                                            onClick={() => {
                                                                setCallResult(opt.value);
                                                                if (opt.value === 'busy') {
                                                                    const now = new Date();
                                                                    now.setHours(now.getHours() + 2);
                                                                    setCallbackTime(now.toISOString().slice(0, 16));
                                                                }
                                                            }}
                                                        >
                                                            {opt.label}
                                                        </Button>
                                                    ))}
                                                </div>
                                            </div>

                                            {/* Calendar picker for callback */}
                                            {callResult === 'busy' && (
                                                <div className="space-y-1 bg-amber-50/50 p-2 rounded-lg border border-amber-100 animate-fade-in">
                                                    <Label className="text-xs font-semibold text-amber-800 flex items-center gap-1">
                                                        <Calendar className="size-3.5" />
                                                        Đặt lịch hẹn gọi lại
                                                    </Label>
                                                    <Input
                                                        type="datetime-local"
                                                        className="bg-white border-amber-200"
                                                        value={callbackTime}
                                                        onChange={(e) => setCallbackTime(e.target.value)}
                                                    />
                                                </div>
                                            )}

                                            {/* Ghi chú tác nghiệp nhanh */}
                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-semibold text-slate-700 flex items-center justify-between">
                                                    <span>Ghi chú chi tiết</span>
                                                    <span className="text-[10px] text-muted-foreground">Chọn nhanh:</span>
                                                </Label>
                                                <div className="flex flex-wrap gap-1 mb-1">
                                                    {['Khách bận', 'Đắt quá', 'Freeship', 'Combo 3sp'].map((tpl) => (
                                                        <button
                                                            key={tpl}
                                                            type="button"
                                                            className="text-[10px] bg-slate-100 hover:bg-slate-200 text-slate-600 px-2 py-0.5 rounded-sm border transition-colors"
                                                            onClick={() => applyQuickTemplate(tpl)}
                                                        >
                                                            +{tpl}
                                                        </button>
                                                    ))}
                                                </div>
                                                <textarea
                                                    className="w-full min-h-[60px] rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                                    placeholder="Nhập thông tin tư vấn, phản hồi khách..."
                                                    value={notes}
                                                    onChange={(e) => setNotes(e.target.value)}
                                                />
                                            </div>

                                            {/* Đóng đơn hàng / Chốt thông tin vận chuyển */}
                                            <div className="pt-2 border-t border-slate-100 space-y-3">
                                                <h4 className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                                    <Truck className="size-3.5 text-slate-500" />
                                                    Thông tin chốt đơn & Vận chuyển
                                                </h4>
                                                
                                                {/* Địa chỉ giao hàng */}
                                                <div className="space-y-1">
                                                    <Label className="text-xs text-slate-600 flex items-center gap-1">
                                                        <MapPin className="size-3 text-slate-400" />
                                                        Địa chỉ nhận hàng
                                                    </Label>
                                                    <Input
                                                        placeholder="Số nhà, tên đường, phường, quận..."
                                                        value={shippingAddress}
                                                        onChange={(e) => setShippingAddress(e.target.value)}
                                                        className="text-xs"
                                                    />
                                                </div>

                                                {/* Đơn vị vận chuyển */}
                                                <div className="grid grid-cols-2 gap-2">
                                                    <div className="space-y-1">
                                                        <Label className="text-xs text-slate-600">Đơn vị vận chuyển</Label>
                                                        <select
                                                            className="w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-xs shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                                            value={shippingProvider}
                                                            onChange={(e) => setShippingProvider(e.target.value)}
                                                        >
                                                            <option value="ghtk">Giaohangtietkiem</option>
                                                            <option value="ghn">Giaohangnhanh</option>
                                                            <option value="viettel_post">ViettelPost</option>
                                                            <option value="jnt">J&T Express</option>
                                                        </select>
                                                    </div>
                                                    <div className="space-y-1">
                                                        <Label className="text-xs text-slate-600">Thu COD (Tiền thu KH)</Label>
                                                        <Input
                                                            type="number"
                                                            value={amountToCollect}
                                                            onChange={(e) => setAmountToCollect(e.target.value)}
                                                            className="text-xs h-8"
                                                        />
                                                    </div>
                                                </div>

                                                {/* Product List summary */}
                                                {selectedOrder.products?.length > 0 && (
                                                    <div className="bg-slate-50 p-2.5 rounded-lg border text-xs space-y-1.5">
                                                        <div className="font-semibold text-slate-700 flex items-center gap-1">
                                                            <ShoppingBag className="size-3.5" />
                                                            Sản phẩm đặt mua
                                                        </div>
                                                        <div className="divide-y divide-slate-100 max-h-[100px] overflow-y-auto">
                                                            {selectedOrder.products.map((p) => (
                                                                <div key={p.productId} className="py-1 flex justify-between">
                                                                    <span className="text-slate-600">{p.productName} <span className="font-medium text-slate-900">x{p.quantity}</span></span>
                                                                    <span className="font-mono text-slate-700">{formatCurrency(p.unitPrice * p.quantity)}</span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                        <div className="pt-1.5 border-t border-slate-200 flex justify-between font-bold text-slate-900">
                                                            <span>Thành tiền:</span>
                                                            <span>{formatCurrency(selectedOrder.total)}</span>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>

                                            {/* Submit & Reset actions */}
                                            <div className="grid grid-cols-5 gap-2 pt-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="col-span-2 text-xs"
                                                    onClick={() => setSelectedOrder(null)}
                                                >
                                                    Hủy
                                                </Button>
                                                <Button
                                                    type="submit"
                                                    disabled={processing || callState === 'connected'}
                                                    className="col-span-3 text-xs bg-primary hover:bg-primary/95 text-primary-foreground gap-1"
                                                >
                                                    <Save className="size-3.5" />
                                                    Cập nhật & Chốt đơn
                                                </Button>
                                            </div>
                                        </form>
                                    </CardContent>
                                </Card>
                            </div>
                        ) : (
                            <Card className="h-full border-dashed min-h-[300px] flex flex-col items-center justify-center text-center p-6 text-muted-foreground bg-slate-50/50">
                                <div className="p-3 bg-white border rounded-full shadow-sm mb-3">
                                    <Phone className="size-6 text-slate-400" />
                                </div>
                                <h3 className="font-bold text-slate-800 mb-1">Chưa chọn đơn tác nghiệp</h3>
                                <p className="text-xs max-w-[220px]">
                                    Hãy chọn một khách hàng từ hàng đợi lead ở bên trái để bắt đầu cuộc gọi và xử lý đơn.
                                </p>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

