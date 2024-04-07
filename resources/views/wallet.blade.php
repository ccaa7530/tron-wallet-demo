@extends('layouts.app')
 
@section('title', 'My Wallet')
 
@section('content')
  <div class="flex w-full flex-wrap gap-3">
    <div class="w-full basis-full justify-start items-center">
      <form action="{{ route('createTestUser') }}" method="post">
        <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 me-2 mb-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">
          新增會員
        </button>
        @csrf
      </form>
    </div>
    <div class="w-full basis-full justify-start items-center border border-gray-200 rounded-md bg-gray-500">
      <div>Admin Account: {{ $admin_account['address'] }}, balance: {{ $admin_account['balance'] }}</div>
      <form action="{{ route('wallet.transfer') }}" method="post" class="flex flex-1 w-[500px] flex-col gap-3 p-10">
        <input type="text" value="" name="from_address" class="text-black w-full" placeholder="轉帳地址" />
        <input type="text" value="" name="to_address" class="text-black w-full" placeholder="到帳地址" />
        <input type="text" value="" name="amount" class="text-black w-full" placeholder="TRX" />
        <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800 flex items-center justify-center">
          轉帳
          <svg class="rtl:rotate-180 w-3.5 h-3.5 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9"/>
          </svg>
        </button>
        @csrf
      </form>
    </div>
    @foreach ($users as $user)
      <div class="max-w-sm p-6 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700 h-auto w-[400px] flex flex-col">
        <div class="flex justify-between items-center">
          <h5 class="mb-2 text-2xl font-bold tracking-tight text-gray-900 dark:text-white flex-1">
            {{ $user->name }}
          </h5>
          @if ($user->wallet)
          <a href="{{ route('wallet.detail', [ 'address' => $user->wallet->address_base58 ] ) }}" class="h-6 mr-2">
          <svg class="h-6 w-6 text-teal-500"  fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
          </a>
          <button onclick="copyTextToClipboard('{{ $user->wallet->address_base58 }}')" class="h-6">
            <svg class="h-6 w-6 text-red-500"  width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">  <path stroke="none" d="M0 0h24v24H0z"/>  <rect x="8" y="8" width="12" height="12" rx="2" />  <path d="M16 8v-2a2 2 0 0 0 -2 -2h-8a2 2 0 0 0 -2 2v8a2 2 0 0 0 2 2h2" /></svg>
          </button>
          @endif
        </div>
        @if ($user->wallet)
          <div class="mb-3 font-normal text-gray-700 dark:text-gray-400">
            {{ $user->wallet->address_base58 }}
          </div>
          <div class="h-full flex items-end flex-col gap-1">
            @if (isset($user->wallet_info['address']))
              <span class="text-4xl text-white">
                {{ $user->wallet->getTrxFormat(true) }}
                <small class="dark:text-gray-400">TRX</small>
              </span>
            @else
              <form action="{{ route('wallet.activeAccount') }}" method="post">
                <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 me-2 mb-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800 flex items-center justify-center">
                  激活帳號
                  <svg class="rtl:rotate-180 w-3.5 h-3.5 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9"/>
                  </svg>
                </button>
                <input type="hidden" value="{{ $user->id }}" name="user_id" />
                @csrf
              </form>
            @endif
          </div>
        @else
          <form action="{{ route('wallet.create') }}" method="post">
            <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 me-2 mb-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800 flex items-center justify-center">
              生成錢包地址
              <svg class="rtl:rotate-180 w-3.5 h-3.5 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9"/>
              </svg>
            </button>
            <input type="hidden" value="{{ $user->id }}" name="user_id" />
            @csrf
          </form>
        @endif
      </div>
    @endforeach
  </div>

@endsection

@section('script')

<script>
function copyTextToClipboard(address) {
  // 创建一个临时的textarea元素
  var textarea = document.createElement("textarea");
  textarea.value = address;

  // 将textarea元素添加到文档中
  document.body.appendChild(textarea);

  // 选择textarea中的文本
  textarea.select();

  // 复制文本到剪贴板
  document.execCommand("copy");

  // 移除textarea元素
  document.body.removeChild(textarea);
}
</script>

@endsection